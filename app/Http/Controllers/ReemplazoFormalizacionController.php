<?php

namespace App\Http\Controllers;

use App\Actions\Reemplazos\FormalizarReemplazoAction;
use App\Http\Requests\FormalizarReemplazoRequest;
use App\Models\Tramite;
use Illuminate\Http\RedirectResponse;

class ReemplazoFormalizacionController extends Controller
{
    public function store(FormalizarReemplazoRequest $request, Tramite $tramite, FormalizarReemplazoAction $formalizar): RedirectResponse
    {
        abort_unless($tramite->tipoTramite()->where('codigo', 'REEMPLAZO')->exists() && $tramite->reemplazo()->exists(), 404);
        $formalizar->execute($tramite, $request->safe()->except('documento_final'), $request->user(), $request->file('documento_final'));

        return redirect()->route('gestion-personas.reemplazos.show', $tramite)->with('status', 'Reemplazo formalizado e incorporado a la dotación.');
    }
}
