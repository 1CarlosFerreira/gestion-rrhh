<?php

namespace App\Http\Controllers;

use App\Models\Tramite;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        if ($this->usesGestionPersonasExperience($request)) {
            return $this->gestionPersonasDashboard($request);
        }

        $base = Tramite::query()->visiblePara($request->user());
        $drafts = (clone $base)->whereHas('estadoTramite', fn ($query) => $query->where('codigo', 'BORRADOR'))->count();
        $inReview = (clone $base)->whereHas('estadoTramite', fn ($query) => $query->whereIn('codigo', ['ENVIADA_GESTION_PERSONAS', 'EN_REVISION', 'PLANILLA_DISPONIBLE', 'EN_REVISION_JEFATURA']))->count();
        $returned = (clone $base)->whereHas('estadoTramite', fn ($query) => $query->where('codigo', 'DEVUELTA_CORRECCION'))->count();
        $formalized = (clone $base)->whereNotNull('finalized_at')->count();
        $attention = (clone $base)->with(['tipoTramite', 'unidadServicio', 'estadoTramite'])
            ->whereHas('estadoTramite', fn ($query) => $query->whereIn('codigo', ['DEVUELTA_CORRECCION', 'EN_REVISION_JEFATURA']))
            ->latest('updated_at')->limit(6)->get();
        $recent = (clone $base)->with(['tipoTramite', 'estadoTramite'])
            ->where('created_by', $request->user()->id)->latest('updated_at')->limit(6)->get();

        return view('dashboard', compact('drafts', 'inReview', 'returned', 'formalized', 'attention', 'recent'));
    }

    private function usesGestionPersonasExperience(Request $request): bool
    {
        return $request->user()->can('reemplazos.revisar_personal')
            && ! $request->user()->can('reemplazos.crear');
    }

    private function gestionPersonasDashboard(Request $request): View
    {
        $base = Tramite::query()
            ->visiblePara($request->user())
            ->whereHas('tipoTramite', fn (Builder $query) => $query->where('codigo', 'REEMPLAZO'));

        $newForReview = (clone $base)->whereHas('estadoTramite', fn (Builder $query) => $query->where('codigo', 'ENVIADA_GESTION_PERSONAS'))->count();
        $inReview = (clone $base)->whereHas('estadoTramite', fn (Builder $query) => $query->where('codigo', 'EN_REVISION'))->count();
        $returned = (clone $base)->whereHas('estadoTramite', fn (Builder $query) => $query->where('codigo', 'DEVUELTA_CORRECCION'))->count();
        $readyToGenerate = (clone $base)->whereHas('estadoTramite', fn (Builder $query) => $query->where('codigo', 'LISTA_GENERAR_DOCUMENTO'))->count();

        $attention = (clone $base)
            ->with(['tipoTramite', 'unidadServicio', 'estadoTramite', 'creador', 'historial' => fn ($query) => $query->whereIn('action_code', ['INICIAR_REVISION', 'DEVOLVER_CORRECCION'])->with('usuario')])
            ->whereHas('estadoTramite', fn (Builder $query) => $query->whereIn('codigo', ['ENVIADA_GESTION_PERSONAS', 'EN_REVISION', 'LISTA_GENERAR_DOCUMENTO']))
            ->latest('updated_at')
            ->limit(6)
            ->get();

        $recent = (clone $base)
            ->with(['tipoTramite', 'unidadServicio', 'estadoTramite'])
            ->whereNotNull('submitted_at')
            ->latest('updated_at')
            ->limit(6)
            ->get();

        return view('gestion-personas.dashboard', compact('newForReview', 'inReview', 'returned', 'readyToGenerate', 'attention', 'recent'));
    }
}
