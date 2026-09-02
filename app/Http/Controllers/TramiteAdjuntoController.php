<?php

namespace App\Http\Controllers;

use App\Actions\Tramites\Adjuntos\AnularAdjunto;
use App\Actions\Tramites\Adjuntos\CargarAdjunto;
use App\Http\Requests\StoreTramiteAdjuntoRequest;
use App\Models\Tramite;
use App\Models\TramiteAdjunto;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TramiteAdjuntoController extends Controller
{
    public function store(StoreTramiteAdjuntoRequest $request, Tramite $tramite, CargarAdjunto $cargar): RedirectResponse
    {
        $cargar->execute($tramite, $request->file('archivo'), $request->user(), $request->integer('tipo_documento_id') ?: null, $request->integer('persona_id') ?: null);

        return back()->with('status', 'Adjunto cargado.');
    }

    public function version(StoreTramiteAdjuntoRequest $request, Tramite $tramite, TramiteAdjunto $adjunto, CargarAdjunto $cargar): RedirectResponse
    {
        $cargar->execute($tramite, $request->file('archivo'), $request->user(), null, null, $adjunto);

        return back()->with('status', 'Nueva versión cargada.');
    }

    public function download(Tramite $tramite, TramiteAdjunto $adjunto): StreamedResponse
    {
        if ($adjunto->tramite_id !== $tramite->id || ! auth()->user()->can('tramites.adjuntos.descargar') || Gate::denies('view', $tramite)) {
            throw new AuthorizationException;
        }
        abort_unless(Storage::disk('private')->exists($adjunto->storage_path), 404);

        return Storage::disk('private')->download($adjunto->storage_path, $adjunto->original_name, ['Content-Type' => $adjunto->mime_type]);
    }

    public function view(Tramite $tramite, TramiteAdjunto $adjunto): StreamedResponse
    {
        if ($adjunto->tramite_id !== $tramite->id || $adjunto->mime_type !== 'application/pdf' || ! auth()->user()->can('tramites.adjuntos.descargar') || Gate::denies('view', $tramite)) {
            throw new AuthorizationException;
        }
        abort_unless(Storage::disk('private')->exists($adjunto->storage_path), 404);

        return Storage::disk('private')->response($adjunto->storage_path, $adjunto->original_name, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$adjunto->original_name.'"',
        ]);
    }

    public function annul(Tramite $tramite, TramiteAdjunto $adjunto, AnularAdjunto $anular): RedirectResponse
    {
        $anular->execute($tramite, $adjunto, auth()->user());

        return back()->with('status', 'Adjunto anulado.');
    }
}
