<?php

namespace App\Http\Controllers;

use App\Actions\Tramites\ConsultarTramites;
use App\Models\EstadoTramite;
use App\Models\TipoTramite;
use App\Models\Tramite;
use App\Models\UnidadServicio;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class GestionPersonasBandejaController extends Controller
{
    public function __invoke(Request $request, ConsultarTramites $consultar): View
    {
        Gate::authorize('viewAny', Tramite::class);

        return view('gestion-personas.bandeja', [
            'tramites' => $consultar->execute($request->user(), $request->only(['tipo_tramite_id', 'estado_tramite_id', 'unidad_servicio_id']))->paginate(20)->withQueryString(),
            'tipos' => TipoTramite::query()->where('activo', true)->orderBy('nombre')->get(),
            'estados' => EstadoTramite::query()->with('tipoTramite')->where('activo', true)->orderBy('nombre')->get(),
            'unidades' => UnidadServicio::query()->where('activo', true)->orderBy('nombre')->get(),
        ]);
    }
}
