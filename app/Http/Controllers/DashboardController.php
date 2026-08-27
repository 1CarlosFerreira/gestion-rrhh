<?php

namespace App\Http\Controllers;

use App\Models\Tramite;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $base = Tramite::query()->visiblePara($request->user());
        $active = (clone $base)->whereNull('finalized_at')->count();
        $returned = (clone $base)->whereHas('estadoTramite', fn ($query) => $query->where('codigo', 'DEVUELTA_CORRECCION'))->count();
        $attention = (clone $base)->with(['tipoTramite', 'unidadServicio', 'estadoTramite'])->whereHas('estadoTramite', fn ($query) => $query->whereIn('codigo', ['DEVUELTA_CORRECCION', 'EN_REVISION_JEFATURA']))->latest()->limit(8)->get();
        $formalized = (clone $base)->whereNotNull('finalized_at')->count();

        return view('dashboard', compact('active', 'returned', 'attention', 'formalized'));
    }
}
