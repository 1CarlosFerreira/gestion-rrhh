<?php

namespace App\Http\Controllers;

use App\Actions\DocDigital\RegistrarEnvioDocDigital;
use App\Actions\DocDigital\RegistrarFormalizacionDocDigital;
use App\Http\Requests\RegistrarEnvioDocDigitalRequest;
use App\Http\Requests\RegistrarFormalizacionDocDigitalRequest;
use App\Models\Tramite;
use App\Models\TramiteAdjunto;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;

class DocDigitalController extends Controller
{
    public function store(RegistrarEnvioDocDigitalRequest $request, Tramite $tramite, RegistrarEnvioDocDigital $registrar): RedirectResponse
    {
        $registrar->execute($tramite, TramiteAdjunto::query()->findOrFail($request->integer('adjunto_enviado_id')), CarbonImmutable::parse($request->string('fecha_envio')), $request->user(), $request->input('identificador_externo'), $request->input('observacion_envio'));

        return back()->with('status', 'Envío a DocDigital registrado.');
    }

    public function formalize(RegistrarFormalizacionDocDigitalRequest $request, Tramite $tramite, RegistrarFormalizacionDocDigital $registrar): RedirectResponse
    {
        $registrar->execute($tramite, $request->file('archivo_final'), CarbonImmutable::parse($request->string('fecha_formalizacion')), $request->user(), $request->input('identificador_externo'), $request->input('observacion_formalizacion'));

        return back()->with('status', 'Formalización DocDigital registrada.');
    }
}
