<?php

namespace App\Http\Controllers;

use App\Actions\Tramites\TransicionarTramite;
use App\Http\Requests\SaveBorradorReemplazoRequest;
use App\Models\Persona;
use App\Models\TipoDocumento;
use App\Models\TipoReemplazo;
use App\Models\Tramite;
use App\Models\UnidadOrganizacional;
use App\Services\Reemplazos\AlcanceSolicitudReemplazoService;
use App\Services\Reemplazos\BorradorReemplazoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ReemplazoController extends Controller
{
    public function create(Request $request, AlcanceSolicitudReemplazoService $alcance): View
    {
        Gate::authorize('crear-reemplazo');

        return $this->form(null, $request, $alcance);
    }

    public function store(SaveBorradorReemplazoRequest $request, AlcanceSolicitudReemplazoService $alcance, BorradorReemplazoService $service): RedirectResponse
    {
        $unidad = UnidadOrganizacional::query()->findOrFail($request->integer('unidad_organizacional_id'));
        abort_unless($alcance->tienePermisoYAlcance($request->user(), 'reemplazos.crear', $unidad, today()), 403);
        $tramite = DB::transaction(function () use ($request, $service, $unidad): Tramite {
            $datos = $this->datos($request);
            $datos['reemplazante_id'] = $this->resolverReemplazante($request, $datos['reemplazante_id'] ?? null);

            return $service->crear($unidad, $datos, $request->user());
        });

        return redirect()->route('reemplazos.edit', $tramite)->with('status', 'Borrador guardado. Ya puede adjuntar documentos.');
    }

    public function edit(Request $request, Tramite $tramite, AlcanceSolicitudReemplazoService $alcance): View
    {
        $this->autorizarTramite($tramite, 'editar-reemplazo');

        return $this->form($tramite->load(['reemplazo.funcionario', 'reemplazo.reemplazante', 'adjuntos.tipoDocumento']), $request, $alcance);
    }

    public function show(Tramite $tramite): View
    {
        abort_unless($tramite->tipoTramite()->where('codigo', 'REEMPLAZO')->exists() && $tramite->reemplazo()->exists(), 404);
        Gate::authorize('view', $tramite);

        return view('reemplazos.show', [
            'tramite' => $tramite->load([
                'tipoTramite',
                'estadoTramite',
                'unidadOrganizacional',
                'creador',
                'reemplazo.funcionario',
                'reemplazo.reemplazante',
                'reemplazo.tipoReemplazo',
                'adjuntos.tipoDocumento',
            ]),
        ]);
    }

    public function update(SaveBorradorReemplazoRequest $request, Tramite $tramite, AlcanceSolicitudReemplazoService $alcance, BorradorReemplazoService $service): RedirectResponse
    {
        $this->autorizarTramite($tramite, 'editar-reemplazo');
        $unidad = UnidadOrganizacional::query()->findOrFail($request->integer('unidad_organizacional_id'));
        abort_unless($alcance->tienePermisoYAlcance($request->user(), 'reemplazos.crear', $unidad, today()), 403);
        DB::transaction(function () use ($request, $service, $unidad, $tramite): void {
            $datos = $this->datos($request);
            $datos['reemplazante_id'] = $this->resolverReemplazante($request, $datos['reemplazante_id'] ?? null);
            $service->actualizar($tramite, $unidad, $datos, $request->user());
        });

        return back()->with('status', 'Borrador actualizado.');
    }

    public function send(SaveBorradorReemplazoRequest $request, Tramite $tramite, AlcanceSolicitudReemplazoService $alcance, BorradorReemplazoService $service, TransicionarTramite $transition): RedirectResponse
    {
        $this->autorizarTramite($tramite, 'editar-reemplazo');
        $unidad = UnidadOrganizacional::query()->findOrFail($request->integer('unidad_organizacional_id'));
        abort_unless($alcance->tienePermisoYAlcance($request->user(), 'reemplazos.crear', $unidad, today()), 403);
        DB::transaction(function () use ($request, $tramite, $unidad, $service, $transition): void {
            $datos = $this->datos($request);
            $datos['reemplazante_id'] = $this->resolverReemplazante($request, $datos['reemplazante_id'] ?? null);
            $service->actualizar($tramite, $unidad, $datos, $request->user());
            $tramite = $tramite->refresh()->load('estadoTramite');
            $action = $tramite->estadoTramite?->codigo === 'DEVUELTA_PARA_CORRECCION' ? 'REENVIAR_A_GESTION_PERSONAS' : 'ENVIAR_A_GESTION_PERSONAS';
            $transition->execute($tramite, $action, $request->user());
        });

        return redirect()->route('dashboard')->with('status', 'Solicitud enviada a Gestión de Personas.');
    }

    public function funcionarios(Request $request, AlcanceSolicitudReemplazoService $alcance): JsonResponse
    {
        Gate::authorize('crear-reemplazo');
        $unidad = UnidadOrganizacional::query()->findOrFail($request->integer('unidad_organizacional_id'));
        abort_unless($alcance->tienePermisoYAlcance($request->user(), 'reemplazos.crear', $unidad, today()), 403);
        $fecha = $request->date('fecha')?->toDateString() ?? today()->toDateString();
        $personas = Persona::query()->where('active', true)->whereHas('vinculosDotacion', fn ($q) => $q->where('unidad_organizacional_id', $unidad->id)->vigentesEn($fecha))->when($request->filled('buscar'), fn ($q) => $q->buscar($request->string('buscar')))->limit(30)->get()->map(fn ($p) => ['id' => $p->id, 'nombre' => $p->nombre_completo, 'rut' => $p->rut]);

        return response()->json($personas);
    }

    private function autorizarTramite(Tramite $tramite, string $ability): void
    {
        abort_unless($tramite->tipoTramite()->where('codigo', 'REEMPLAZO')->exists() && $tramite->reemplazo()->exists(), 404);
        Gate::authorize($ability, $tramite);
    }

    private function datos(SaveBorradorReemplazoRequest $request): array
    {
        return collect($request->validated())->only(['funcionario_id', 'reemplazante_id', 'tipo_reemplazo_id', 'fecha_funcionario_desde', 'fecha_funcionario_hasta', 'fecha_reemplazante_desde', 'fecha_reemplazante_hasta', 'justificacion'])->all();
    }

    private function resolverReemplazante(SaveBorradorReemplazoRequest $request, ?int $existente): ?int
    {
        if (! $request->filled('nuevo_reemplazante_rut')) {
            return $existente;
        }
        $persona = Persona::query()->firstOrCreate(['rut' => $request->string('nuevo_reemplazante_rut')->toString()], ['nombres' => $request->string('nuevo_reemplazante_nombres')->toString(), 'apellido_paterno' => $request->input('nuevo_reemplazante_apellido_paterno'), 'apellido_materno' => $request->input('nuevo_reemplazante_apellido_materno'), 'active' => true]);

        return $persona->id;
    }

    private function form(?Tramite $tramite, Request $request, AlcanceSolicitudReemplazoService $alcance): View
    {
        $unidades = $alcance->unidadesAutorizadas($request->user(), today());
        $seleccionada = (int) old('unidad_organizacional_id', $tramite?->unidad_organizacional_id ?? ($unidades->count() === 1 ? $unidades->first()->id : 0));
        $fechaFuncionario = old('fecha_funcionario_desde', $tramite?->reemplazo?->fecha_funcionario_desde?->toDateString() ?? today()->toDateString());
        $funcionarios = $seleccionada ? Persona::query()->where('active', true)->whereHas('vinculosDotacion', fn ($q) => $q->where('unidad_organizacional_id', $seleccionada)->vigentesEn($fechaFuncionario))->orderBy('apellido_paterno')->get() : collect();

        return view('reemplazos.form', ['tramite' => $tramite, 'detalle' => $tramite?->reemplazo, 'unidades' => $unidades, 'funcionarios' => $funcionarios, 'personas' => Persona::query()->where('active', true)->orderBy('apellido_paterno')->limit(200)->get(), 'tipos' => TipoReemplazo::query()->where('activo', true)->orderBy('orden')->get(), 'tiposDocumento' => TipoDocumento::query()->where('active', true)->orderBy('nombre')->get()]);
    }
}
