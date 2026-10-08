<?php

namespace App\Http\Controllers;

use App\Actions\Tramites\TransicionarTramite;
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
use App\Support\Tramites\ResolverRetornoTramite;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
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

        return redirect()->route('reemplazos.edit', $tramite)->with('status', 'Borrador guardado. Ya puede adjuntar documentos.');
    }

    public function edit(Request $request, Tramite $tramite, ContextoSolicitudContratoService $contexto): View
    {
        $this->autorizarTramite($tramite, 'editar-reemplazo');

        return $this->form($tramite->load(['solicitudContrato', 'reemplazo.funcionario', 'reemplazo.reemplazante', 'adjuntos.tipoDocumento']), $request, $contexto);
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

        return back()->with('status', 'Borrador actualizado.');
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

        $personas = Persona::query()->with(['vinculosDotacion' => fn ($query) => $query->with(['unidad', 'estamento', 'profesion', 'calidadContractual'])->orderByDesc('vigente_desde')])->where('active', true)->orderBy('apellido_paterno')->limit(200)->get();

        return view('reemplazos.form', ['tramite' => $tramite, 'solicitudContrato' => $tramite?->solicitudContrato, 'legacy' => $legacy, 'detalle' => $tramite?->reemplazo, 'unidades' => $unidades, 'funcionarios' => $funcionarios, 'personas' => $personas, 'estamentos' => Estamento::query()->where('activo', true)->orderBy('nombre')->get(), 'profesiones' => Profesion::query()->where('activo', true)->orderBy('nombre')->get(), 'calidades' => CalidadContractual::query()->where('activo', true)->orderBy('orden')->orderBy('nombre')->get(), 'tipos' => TipoReemplazo::query()->where('activo', true)->orderBy('orden')->get(), 'tiposDocumento' => TipoDocumento::query()->where('active', true)->orderBy('nombre')->get()]);
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
