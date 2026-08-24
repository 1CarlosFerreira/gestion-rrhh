<?php

namespace App\Http\Controllers;

use App\Actions\Tramites\ConsultarTramites;
use App\Actions\Tramites\CrearTramite;
use App\Http\Requests\StoreTramiteRequest;
use App\Models\EstadoTramite;
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
            'tipos' => TipoTramite::query()->where('activo', true)->orderBy('nombre')->get(),
            'unidades' => $unidades,
        ]);
    }

    public function store(StoreTramiteRequest $request, CrearTramite $crear): RedirectResponse
    {
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
        $tramite->load(['tipoTramite', 'unidadServicio', 'estadoTramite', 'creador', 'historial.usuario', 'historial.estadoOrigen', 'historial.estadoDestino']);

        return view('tramites.show', compact('tramite'));
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
