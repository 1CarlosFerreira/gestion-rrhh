<?php

namespace App\Http\Controllers;

use App\Actions\Tramites\ConsultarTramites;
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

        abort_unless($request->user()->can('reemplazos.revisar_personal'), 403);

        return view('gestion-personas.bandeja', [
            'tramites' => $consultar->execute($request->user(), $request->only(['estado_grupo', 'codigo', 'unidad_servicio_id']))
                ->whereHas('tipoTramite', fn ($query) => $query->where('codigo', 'REEMPLAZO'))
                ->whereNotNull('submitted_at')
                ->with(['historial' => fn ($query) => $query->whereIn('action_code', ['INICIAR_REVISION', 'DEVOLVER_CORRECCION'])->with('usuario')])
                ->paginate(20)
                ->withQueryString(),
            'unidades' => UnidadServicio::query()->where('activo', true)->orderBy('nombre')->get(),
        ]);
    }
}
