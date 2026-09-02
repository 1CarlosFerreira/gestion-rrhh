<?php

namespace App\Http\Controllers;

use App\Models\HorasExtraFuncionario;
use App\Models\Persona;
use App\Models\PersonaUnidadVinculo;
use App\Models\TramiteReemplazo;
use App\Models\UnidadServicio;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class DotacionController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $unidades = $user->can('tramites.ver_todos')
            ? UnidadServicio::query()->where('activo', true)->orderBy('nombre')->get()
            : $user->unidadesHabilitadas()->where('activo', true)->orderBy('nombre')->get();

        $unidad = $request->filled('unidad_id') ? UnidadServicio::query()->findOrFail($request->integer('unidad_id')) : $unidades->first();
        $vinculos = null;

        if ($unidad) {
            Gate::authorize('viewDotacion', $unidad);
            $vinculos = PersonaUnidadVinculo::query()
                ->where('unidad_servicio_id', $unidad->id)
                ->whereIn('status', PersonaUnidadVinculo::ESTADOS_OPERATIVOS)
                ->whereHas('persona', function ($query) use ($request): void {
                    $query->where('active', true)
                        ->when($request->filled('q'), fn ($query) => $query->buscar($request->string('q')->toString()));
                })
                ->with(['persona', 'estamento', 'profesion'])
                ->orderBy('status')->orderBy('id')->paginate(12)->withQueryString();
        }

        return view('dotacion.index', compact('unidades', 'unidad', 'vinculos'));
    }

    public function show(Request $request, Persona $persona): View
    {
        Gate::authorize('viewFicha', $persona);
        $allowedUnits = $request->user()->can('tramites.ver_todos') ? null : $request->user()->unidadesHabilitadas()->pluck('unidades_servicios.id');
        $vinculos = $persona->vinculos()->with(['unidad', 'estamento', 'profesion'])->when($allowedUnits, fn ($query) => $query->whereIn('unidad_servicio_id', $allowedUnits))->orderByDesc('start_date')->orderByDesc('id')->get();
        $reemplazos = TramiteReemplazo::query()->where(fn ($query) => $query->where('funcionario_id', $persona->id)->orWhere('reemplazante_id', $persona->id))->whereHas('tramite', fn ($query) => $query->visiblePara($request->user()))->with(['ausencia.coberturas.reemplazante', 'ausencia.coberturas.tramite.estadoTramite', 'ausencia.coberturas.tramite.documentosGenerados.adjunto', 'tramite.tipoTramite', 'tramite.unidadServicio', 'tramite.estadoTramite'])->latest()->paginate(10, ['*'], 'reemplazos_page')->withQueryString();
        $horasExtra = HorasExtraFuncionario::query()->where('persona_id', $persona->id)->whereHas('tramiteHorasExtra.tramite', fn ($query) => $query->visiblePara($request->user()))->with(['tramiteHorasExtra.tramite.unidadServicio', 'tramiteHorasExtra.tramite.estadoTramite'])->latest()->paginate(10, ['*'], 'horas_extra_page')->withQueryString();
        $actual = $vinculos->first(fn ($item) => $item->status === 'ACTIVO' && (! $item->start_date || $item->start_date->lte(now())) && (! $item->end_date || $item->end_date->gte(now())));

        return view('dotacion.show', compact('persona', 'vinculos', 'reemplazos', 'horasExtra', 'actual'));
    }
}
