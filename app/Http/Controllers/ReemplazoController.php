<?php

namespace App\Http\Controllers;

use App\Actions\Reemplazos\RegistrarRespaldoSolicitudTransitoriaAction;
use App\Actions\Tramites\TransicionarTramite;
use App\Http\Requests\RegistrarRespaldoTransitorioRequest;
use App\Http\Requests\SaveBorradorReemplazoRequest;
use App\Models\CalidadContractual;
use App\Models\Estamento;
use App\Models\Persona;
use App\Models\Profesion;
use App\Models\TipoDocumento;
use App\Models\TipoReemplazo;
use App\Models\Tramite;
use App\Models\UnidadOrganizacional;
use App\Services\Reemplazos\AlcanceSolicitudReemplazoService;
use App\Services\Reemplazos\BorradorReemplazoService;
use App\Services\SolicitudesContrato\ContextoSolicitudContratoService;
use App\Support\Rut\Rut;
use App\Support\Tramites\ResolverRetornoTramite;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ReemplazoController extends Controller
{
    public function create(Request $request, ContextoSolicitudContratoService $contexto): View
    {
        Gate::authorize('crear-reemplazo');

        return $this->form(null, $request, $contexto);
    }

    public function store(SaveBorradorReemplazoRequest $request, BorradorReemplazoService $service): RedirectResponse
    {
        $tramite = DB::transaction(function () use ($request, $service): Tramite {
            $datos = $this->datos($request);
            $datos['reemplazante_id'] = $this->resolverReemplazante($request, $datos['reemplazante_id'] ?? null);

            return $service->crear($this->contextoV3($request), $datos, $request->user());
        });

        $respuesta = redirect()->route('reemplazos.edit', $tramite)->with('status', 'Borrador guardado. Ya puede adjuntar documentos.');
        if ($request->input('modo_reemplazante') === 'nuevo' && ! $request->filled('nuevo_reemplazante_rut')) {
            $respuesta->with('modo_reemplazante', 'nuevo');
        }

        return $respuesta;
    }

    public function edit(Request $request, Tramite $tramite, ContextoSolicitudContratoService $contexto): View
    {
        $this->autorizarTramite($tramite, 'editar-reemplazo');

        return $this->form($tramite->load(['solicitudContrato', 'reemplazo.funcionario', 'reemplazo.reemplazante', 'adjuntos.tipoDocumento', 'adjuntos.documentoGenerado']), $request, $contexto);
    }

    public function show(Request $request, Tramite $tramite, ResolverRetornoTramite $resolverRetorno): View
    {
        abort_unless($tramite->tipoTramite()->where('codigo', 'REEMPLAZO')->exists() && $tramite->reemplazo()->exists(), 404);
        Gate::authorize('view', $tramite);

        return view('reemplazos.show', [
            'tramite' => $tramite->load([
                'tipoTramite',
                'estadoTramite',
                'unidadOrganizacional',
                'creador',
                'solicitudContrato.unidadSolicitante',
                'solicitudContrato.unidadOrigen',
                'solicitudContrato.unidadDestino',
                'solicitudContrato.autoridad',
                'reemplazo.funcionario',
                'reemplazo.reemplazante',
                'reemplazo.tipoReemplazo',
                'adjuntos.tipoDocumento',
                'historial.usuario',
            ]),
            'retorno' => $resolverRetorno->resolve($request),
        ]);
    }

    public function update(SaveBorradorReemplazoRequest $request, Tramite $tramite, BorradorReemplazoService $service): RedirectResponse
    {
        $this->autorizarTramite($tramite, 'editar-reemplazo');
        DB::transaction(function () use ($request, $service, $tramite): void {
            $datos = $this->datos($request);
            $datos['reemplazante_id'] = $this->resolverReemplazante($request, $datos['reemplazante_id'] ?? null);
            $service->actualizar($tramite, $this->contexto($request), $datos, $request->user());
        });

        $respuesta = back()->with('status', 'Borrador actualizado.');
        if ($request->input('modo_reemplazante') === 'nuevo' && ! $request->filled('nuevo_reemplazante_rut')) {
            $respuesta->with('modo_reemplazante', 'nuevo');
        }

        return $respuesta;
    }

    public function send(SaveBorradorReemplazoRequest $request, Tramite $tramite, BorradorReemplazoService $service, TransicionarTramite $transition): RedirectResponse
    {
        $this->autorizarTramite($tramite, 'editar-reemplazo');
        DB::transaction(function () use ($request, $tramite, $service, $transition): void {
            $datos = $this->datos($request);
            $datos['reemplazante_id'] = $this->resolverReemplazante($request, $datos['reemplazante_id'] ?? null);
            $service->actualizar($tramite, $this->contexto($request), $datos, $request->user());
            $tramite = $tramite->refresh()->load('estadoTramite');
            $action = $tramite->estadoTramite?->codigo === 'DEVUELTA_PARA_CORRECCION' ? 'REENVIAR_A_GESTION_PERSONAS' : 'ENVIAR_A_GESTION_PERSONAS';
            $transition->execute($tramite, $action, $request->user());
        });

        return redirect()->route('dashboard')->with('status', 'Solicitud enviada a Gestión de Personas.');
    }

    public function registrarRespaldo(RegistrarRespaldoTransitorioRequest $request, Tramite $tramite, RegistrarRespaldoSolicitudTransitoriaAction $registrar): RedirectResponse
    {
        $registrar->execute($tramite, $request->user(), $request->validated());

        return redirect()->route('reemplazos.edit', $tramite)->with('status', 'Respaldo transitorio registrado.');
    }

    public function funcionarios(Request $request, ContextoSolicitudContratoService $contexto, AlcanceSolicitudReemplazoService $alcance): JsonResponse
    {
        abort_unless($request->user()->active && $request->user()->can('reemplazos.crear'), 403);
        $unidadId = $request->integer('unidad_origen_id') ?: $request->integer('unidad_organizacional_id');
        $unidad = UnidadOrganizacional::query()->findOrFail($unidadId);
        $autorizadas = $request->filled('unidad_origen_id')
            ? $contexto->unidadesAutorizadas($request->user(), today())
            : $alcance->unidadesAutorizadas($request->user(), today());
        abort_unless($autorizadas->contains('id', $unidad->id), 403);
        $fecha = $request->date('fecha')?->toDateString() ?? today()->toDateString();
        $personas = Persona::query()
            ->with(['vinculosDotacion' => fn ($query) => $query
                ->with(['unidad', 'estamento', 'profesion', 'calidadContractual'])
                ->where('unidad_organizacional_id', $unidad->id)
                ->vigentesEn($fecha)
                ->orderByDesc('vigente_desde')])
            ->where('active', true)
            ->whereHas('vinculosDotacion', fn ($q) => $q->where('unidad_organizacional_id', $unidad->id)->vigentesEn($fecha))
            ->when($request->filled('buscar'), fn ($q) => $q->buscar($request->string('buscar')))
            ->limit(30)
            ->get()
            ->map(fn ($persona) => $this->funcionarioParaFormulario($persona));

        return response()->json($personas);
    }

    public function personas(Request $request): JsonResponse
    {
        Gate::authorize('crear-reemplazo');
        $rutIngresado = $request->query('rut');
        $busquedaIngresada = $request->query('buscar');
        $rutExacto = null;
        if ($rutIngresado !== null) {
            if (! is_string($rutIngresado) || ! Rut::validate($rutIngresado)) {
                return response()->json([]);
            }
            $rutExacto = Rut::normalize($rutIngresado);
            $consulta = Persona::query()->where('rut', $rutExacto);
        } else {
            if (! is_string($busquedaIngresada)) {
                return response()->json([]);
            }
            $busqueda = trim($busquedaIngresada);
            if (mb_strlen($busqueda) < 3 || mb_strlen($busqueda) > 80 || strpbrk($busqueda, '%_') !== false) {
                return response()->json([]);
            }
            $rut = preg_match('/^[\d.\s]+$/u', $busqueda)
                ? preg_replace('/[.\s]+/u', '', $busqueda)
                : Rut::normalize($busqueda);
            $rutExacto = Rut::validate($busqueda) ? Rut::normalize($busqueda) : null;
            $consulta = Persona::query()->where(function ($query) use ($busqueda, $rut): void {
                $query->where('rut', 'like', $rut.'%')
                    ->orWhere('nombres', 'like', $busqueda.'%')
                    ->orWhere('apellido_paterno', 'like', $busqueda.'%')
                    ->orWhere('apellido_materno', 'like', $busqueda.'%');
            });
        }

        $personas = $consulta->select(['id', 'rut', 'nombres', 'apellido_paterno', 'apellido_materno'])
            ->where('active', true)
            ->with(['vinculosDotacion' => fn ($query) => $query
                ->select(['id', 'persona_id', 'unidad_organizacional_id', 'vigente_desde', 'vigente_hasta'])
                ->with('unidad:id,nombre')
                ->vigentesEn(today())
                ->orderByDesc('vigente_desde')])
            ->orderBy('apellido_paterno')->orderBy('nombres')
            ->limit(10)->get();

        if ($personas->isEmpty() && $rutExacto !== null && Persona::query()->where('rut', $rutExacto)->where('active', false)->exists()) {
            return response()->json([['id' => null, 'nombre' => 'Persona registrada inactiva', 'rut' => $rutExacto, 'unidad_vigente' => null, 'inactiva' => true, 'antecedentes' => []]]);
        }

        return response()->json($personas->map(fn (Persona $persona) => $this->personaParaBusqueda($persona)));
    }

    private function personaParaBusqueda(Persona $persona): array
    {
        $unidadesVigentes = $persona->vinculosDotacion
            ->map(fn ($vinculo) => $vinculo->unidad?->nombre)
            ->filter()->unique()->values();

        return [
            'id' => (string) $persona->id,
            'nombre' => $persona->nombre_completo,
            'rut' => $persona->rut,
            'unidad_vigente' => $unidadesVigentes->implode(' · ') ?: null,
        ];
    }

    private function autorizarTramite(Tramite $tramite, string $ability): void
    {
        abort_unless($tramite->tipoTramite()->where('codigo', 'REEMPLAZO')->exists() && $tramite->reemplazo()->exists(), 404);
        Gate::authorize($ability, $tramite);
    }

    private function datos(SaveBorradorReemplazoRequest $request): array
    {
        $datos = collect($request->validated())->only(['funcionario_id', 'reemplazante_id', 'reemplazante_estamento_id', 'reemplazante_profesion_id', 'reemplazante_calidad_contractual_id', 'reemplazante_cargo_funcion', 'tipo_reemplazo_id', 'fecha_funcionario_desde', 'fecha_funcionario_hasta', 'fecha_reemplazante_desde', 'fecha_reemplazante_hasta', 'justificacion'])->all();
        if (array_key_exists('reemplazante_cargo_funcion', $datos)) {
            $datos['reemplazante_cargo_funcion'] = filled($datos['reemplazante_cargo_funcion']) ? preg_replace('/\s+/u', ' ', trim($datos['reemplazante_cargo_funcion'])) : null;
        }

        return $datos;
    }

    private function resolverReemplazante(SaveBorradorReemplazoRequest $request, ?int $existente): ?int
    {
        if (! $request->filled('nuevo_reemplazante_rut')) {
            return $existente;
        }
        $persona = Persona::query()->firstOrCreate(['rut' => $request->string('nuevo_reemplazante_rut')->toString()], ['nombres' => $request->string('nuevo_reemplazante_nombres')->toString(), 'apellido_paterno' => $request->input('nuevo_reemplazante_apellido_paterno'), 'apellido_materno' => $request->input('nuevo_reemplazante_apellido_materno'), 'active' => true]);
        $tramite = $request->route('tramite');
        if (! $persona->wasRecentlyCreated && (! ($tramite instanceof Tramite) || $tramite->solicitudContrato()->exists())) {
            throw ValidationException::withMessages(['nuevo_reemplazante_rut' => 'Este RUT ya está registrado. Seleccione la persona existente.']);
        }

        return $persona->id;
    }

    private function form(?Tramite $tramite, Request $request, ContextoSolicitudContratoService $contexto): View
    {
        $unidadesOperativas = $contexto->unidadesAutorizadas($request->user(), today());
        $legacy = $tramite !== null && $tramite->solicitudContrato === null;
        $unidades = $legacy ? app(AlcanceSolicitudReemplazoService::class)->unidadesAutorizadas($request->user(), today()) : $unidadesOperativas;
        $seleccionada = (int) old($legacy ? 'unidad_organizacional_id' : 'unidad_origen_id', $tramite?->solicitudContrato?->unidad_origen_id ?? $tramite?->unidad_organizacional_id ?? ($unidades->count() === 1 ? $unidades->first()->id : 0));
        $fechaFuncionario = old('fecha_funcionario_desde', $tramite?->reemplazo?->fecha_funcionario_desde?->toDateString() ?? today()->toDateString());
        $funcionarios = $seleccionada ? Persona::query()
            ->with(['vinculosDotacion' => fn ($query) => $query
                ->with(['unidad', 'estamento', 'profesion', 'calidadContractual'])
                ->where('unidad_organizacional_id', $seleccionada)
                ->vigentesEn($fechaFuncionario)
                ->orderByDesc('vigente_desde')])
            ->where('active', true)
            ->whereHas('vinculosDotacion', fn ($q) => $q->where('unidad_organizacional_id', $seleccionada)->vigentesEn($fechaFuncionario))
            ->orderBy('apellido_paterno')
            ->get() : collect();

        $personas = $legacy
            ? Persona::query()->with(['vinculosDotacion' => fn ($query) => $query->with(['unidad', 'estamento', 'profesion', 'calidadContractual'])->orderByDesc('vigente_desde')])->where('active', true)->orderBy('apellido_paterno')->limit(200)->get()
            : collect();
        $reemplazanteId = (int) ($tramite?->reemplazo?->reemplazante_id ?? 0);
        if ($reemplazanteId > 0 && ! $personas->contains('id', $reemplazanteId)) {
            $seleccionada = Persona::query()
                ->with(['vinculosDotacion' => fn ($query) => $query->with(['unidad', 'estamento', 'profesion', 'calidadContractual'])->orderByDesc('vigente_desde')])
                ->find($reemplazanteId);
            if ($seleccionada !== null) {
                $personas->prepend($seleccionada);
            }
        }
        $rutBuscado = Rut::normalize((string) old('buscar_reemplazante', old('buscar_reemplazante_rut', '')));
        $reemplazanteAnteriorId = (int) old('reemplazante_id', 0);
        if ($reemplazanteAnteriorId > 0 && Rut::validate($rutBuscado) && ! $personas->contains('id', $reemplazanteAnteriorId)) {
            $seleccionada = Persona::query()
                ->with(['vinculosDotacion' => fn ($query) => $query->with(['unidad', 'estamento', 'profesion', 'calidadContractual'])->orderByDesc('vigente_desde')])
                ->whereKey($reemplazanteAnteriorId)->where('rut', $rutBuscado)->where('active', true)->first();
            if ($seleccionada !== null) {
                $personas->prepend($seleccionada);
            }
        }

        return view('reemplazos.form', ['tramite' => $tramite, 'solicitudContrato' => $tramite?->solicitudContrato, 'respaldos' => $tramite?->solicitudContrato?->respaldosRegistrados()->with('versionActual')->get() ?? collect(), 'legacy' => $legacy, 'detalle' => $tramite?->reemplazo, 'unidades' => $unidades, 'funcionarios' => $funcionarios, 'personas' => $personas, 'estamentos' => Estamento::query()->where('activo', true)->orderBy('nombre')->get(), 'profesiones' => Profesion::query()->where('activo', true)->orderBy('nombre')->get(), 'calidades' => CalidadContractual::query()->where('activo', true)->orderBy('orden')->orderBy('nombre')->get(), 'tipos' => TipoReemplazo::query()->where('activo', true)->orderBy('orden')->get(), 'tiposDocumento' => TipoDocumento::query()->where('active', true)->orderBy('nombre')->get()]);
    }

    private function contexto(SaveBorradorReemplazoRequest $request): array|UnidadOrganizacional
    {
        $validado = $request->validated();
        if (isset($validado['unidad_organizacional_id'])) {
            return UnidadOrganizacional::query()->findOrFail($validado['unidad_organizacional_id']);
        }

        return $this->contextoV3($request);
    }

    private function contextoV3(SaveBorradorReemplazoRequest $request): array
    {
        return collect($request->validated())->only([
            'unidad_solicitante_id',
            'unidad_origen_id',
            'unidad_destino_id',
        ])->all();
    }

    private function funcionarioParaFormulario(Persona $persona): array
    {
        $vinculo = $persona->vinculosDotacion->first();

        return [
            'id' => $persona->id,
            'nombre' => $persona->nombre_completo,
            'rut' => $persona->rut,
            'antecedente_laboral' => $vinculo ? [
                'estamento' => $vinculo->estamento?->nombre,
                'profesion' => $vinculo->profesion?->nombre,
                'calidad_contractual' => $vinculo->calidadContractual?->nombre,
                'cargo_funcion' => $vinculo->cargo_funcion,
                'unidad' => $vinculo->unidad?->nombre,
            ] : null,
        ];
    }
}
