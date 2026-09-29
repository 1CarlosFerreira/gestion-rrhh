<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Tramites\ConsultarTramites;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\IndexTramitesRequest;
use App\Models\EstadoTramite;
use App\Models\TipoTramite;
use App\Models\UnidadOrganizacional;
use App\Models\User;
use App\Support\Tramites\PresentarTramiteBandeja;
use Illuminate\View\View;

class TramiteController extends Controller
{
    public function index(
        IndexTramitesRequest $request,
        ConsultarTramites $consultarTramites,
        PresentarTramiteBandeja $presentador,
    ): View {
        $filters = $request->validated();
        $navigationContext = [
            'from' => 'admin_tramites',
            'return' => collect($filters)
                ->only(['buscar', 'tipo', 'estado', 'unidad', 'creador', 'fecha_desde', 'fecha_hasta', 'orden', 'per_page', 'page'])
                ->filter(fn ($value) => $value !== null && $value !== '')
                ->all(),
        ];
        $tramites = $consultarTramites
            ->execute($filters)
            ->paginate($filters['per_page'])
            ->withQueryString()
            ->through(fn ($tramite) => $presentador->make($tramite, $navigationContext));

        $tipos = TipoTramite::query()->orderBy('nombre')->get(['id', 'nombre']);
        $estados = EstadoTramite::query()
            ->with('tipoTramite:id,nombre')
            ->when($filters['tipo'] ?? null, fn ($query, $tipo) => $query->where('tipo_tramite_id', $tipo))
            ->orderBy('tipo_tramite_id')
            ->orderBy('orden')
            ->get(['id', 'tipo_tramite_id', 'nombre']);
        $unidades = UnidadOrganizacional::query()->orderBy('nombre')->get(['id', 'nombre']);
        $creadores = User::query()
            ->whereHas('tramitesCreados')
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('admin.tramites.index', compact('tramites', 'tipos', 'estados', 'unidades', 'creadores'));
    }
}
