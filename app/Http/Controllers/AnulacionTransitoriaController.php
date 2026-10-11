<?php

namespace App\Http\Controllers;

use App\Actions\Reemplazos\AnularSolicitudTransitoriaAction;
use App\Actions\Reemplazos\SolicitarAnulacionTransitoriaAction;
use App\Http\Requests\AnulacionTransitoriaRequest;
use App\Models\Tramite;
use Illuminate\Http\RedirectResponse;

class AnulacionTransitoriaController extends Controller
{
    public function annul(AnulacionTransitoriaRequest $request, Tramite $tramite, AnularSolicitudTransitoriaAction $action): RedirectResponse
    {
        $action->execute($tramite, $request->user(), $request->validated('motivo'));

        return back()->with('status', 'Solicitud anulada. Los compromisos existentes fueron liberados.');
    }

    public function request(AnulacionTransitoriaRequest $request, Tramite $tramite, SolicitarAnulacionTransitoriaAction $action): RedirectResponse
    {
        $action->execute($tramite, $request->user(), $request->validated('motivo'));

        return back()->with('status', 'Petición de anulación registrada para Gestión de Personas.');
    }
}
