<?php

namespace App\Http\Controllers;

use App\Actions\Tramites\ConsultarTramites;
use App\Actions\Tramites\CrearTramite;
use App\Http\Requests\StoreTramiteRequest;
use App\Models\ClasificacionArea;
use App\Models\EstadoTramite;
use App\Models\GradoEus;
use App\Models\Persona;
use App\Models\TipoDocumento;
use App\Models\TipoTramite;
use App\Models\Tramite;
use App\Models\UnidadServicio;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class TramiteController extends Controller
{
    public function index(Request $request, ConsultarTramites $consultar): View
    {
        Gate::authorize('viewAny', Tramite::class);

        return view('tramites.index', [
            'tramites' => $consultar->execute($request->user(), $request->only(['tipo_tramite_id', 'estado_tramite_id', 'unidad_servicio_id', 'codigo', 'desde', 'hasta']))->paginate(20)->withQueryString(),
            ...$this->catalogs($request),
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', Tramite::class);
        $unidades = $request->user()->can('tramites.ver_todos')
            ? UnidadServicio::query()->where('activo', true)->orderBy('nombre')->get()
            : $request->user()->unidadesHabilitadas()->where('activo', true)->orderBy('nombre')->get();

        return view('tramites.create', [
            'tipos' => TipoTramite::query()->where('activo', true)->whereNotIn('codigo', ['REEMPLAZO', 'HORAS_EXTRAORDINARIAS'])->orderBy('nombre')->get(),
            'unidades' => $unidades,
        ]);
    }

    public function store(StoreTramiteRequest $request, CrearTramite $crear): RedirectResponse
    {
        abort_unless($request->user()->can('tramites.ver_todos'), 403);
        $tramite = $crear->execute(
            TipoTramite::query()->findOrFail($request->integer('tipo_tramite_id')),
            UnidadServicio::query()->findOrFail($request->integer('unidad_servicio_id')),
            $request->user(),
        );

        return redirect()->route('tramites.show', $tramite)->with('status', 'Trámite raíz creado.');
    }

    public function show(Tramite $tramite): View
    {
        Gate::authorize('view', $tramite);
        $tramite->load(['tipoTramite', 'unidadServicio', 'estadoTramite', 'creador', 'historial.usuario', 'historial.estadoOrigen', 'historial.estadoDestino', 'adjuntos.tipoDocumento', 'adjuntos.persona', 'adjuntos.cargadoPor', 'documentosGenerados.tipoDocumento', 'documentosGenerados.plantilla', 'documentosGenerados.adjunto', 'documentosGenerados.generadoPor', 'registrosDocDigital.documentoGenerado', 'registrosDocDigital.adjuntoEnviado', 'registrosDocDigital.registradoPor', 'registrosDocDigital.formalizadoPor', 'registrosDocDigital.adjuntoFinal', 'reemplazo.tipoReemplazo', 'reemplazo.funcionario', 'reemplazo.reemplazante', 'reemplazo.estamento', 'reemplazo.profesion', 'revisionReemplazo.gradoEus', 'revisionReemplazo.clasificacionArea', 'revisionReemplazo.completadoPor', 'horasExtra.informeTecnico', 'horasExtra.funcionarios.persona.vinculosOperativos.unidad', 'horasExtra.funcionarios.planillas.adjunto', 'horasExtra.funcionarios.planillas.cargadoPor', 'horasExtra.funcionarios.planillas.revisiones.revisadoPor']);

        return view('tramites.show', [
            'tramite' => $tramite,
            'tiposDocumento' => TipoDocumento::query()->where('active', true)->orderBy('nombre')->get(),
            'personas' => Persona::query()->where('active', true)->orderBy('apellido_paterno')->limit(100)->get(),
            ...($tramite->tipoTramite->codigo === 'REEMPLAZO' ? [
                'grados' => GradoEus::query()->where('activo', true)->orderBy('grado')->get(),
                'clasificaciones' => ClasificacionArea::query()->where('activo', true)->orderBy('nombre')->get(),
            ] : []),
        ]);
    }

    private function catalogs(Request $request): array
    {
        $type = $request->integer('tipo_tramite_id');

        return [
            'tipos' => TipoTramite::query()->where('activo', true)->orderBy('nombre')->get(),
            'estados' => EstadoTramite::query()->with('tipoTramite')->where('activo', true)->when($type, fn ($query) => $query->where('tipo_tramite_id', $type))->orderBy('nombre')->get(),
            'unidades' => UnidadServicio::query()->where('activo', true)->orderBy('nombre')->get(),
        ];
    }
}
