<?php

namespace App\Http\Controllers;

use App\Actions\Reemplazos\GenerarSolicitudReemplazoPdfAction;
use App\Models\DocumentoGenerado;
use App\Models\Tramite;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ReemplazoDocumentoController extends Controller
{
    public function store(Tramite $tramite, GenerarSolicitudReemplazoPdfAction $generar): RedirectResponse
    {
        $this->autorizar($tramite);
        try {
            $generar->execute($tramite, auth()->user());
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors(['documento' => 'No fue posible generar el documento. Intente nuevamente.']);
        }

        return redirect()->route('gestion-personas.reemplazos.show', $tramite)->with('status', 'Documento generado correctamente.');
    }

    public function download(Tramite $tramite, DocumentoGenerado $documento): StreamedResponse
    {
        $this->autorizar($tramite);
        abort_unless($documento->tramite_id === $tramite->id && $documento->status === 'VIGENTE', 404);
        $adjunto = $documento->adjunto;
        abort_unless($adjunto !== null && Storage::disk('private')->exists($adjunto->storage_path), 404);

        return Storage::disk('private')->download($adjunto->storage_path, $adjunto->original_name, ['Content-Type' => 'application/pdf']);
    }

    private function autorizar(Tramite $tramite): void
    {
        abort_unless($tramite->tipoTramite()->where('codigo', 'REEMPLAZO')->exists() && $tramite->reemplazo()->exists(), 404);
        Gate::authorize('generar-documento-reemplazo', $tramite);
    }
}
