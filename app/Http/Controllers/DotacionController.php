<?php

namespace App\Http\Controllers;

use App\Models\PersonaUnidadVinculo;
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
                ->orderBy('status')->paginate(20)->withQueryString();
        }

        return view('dotacion.index', compact('unidades', 'unidad', 'vinculos'));
    }
}
