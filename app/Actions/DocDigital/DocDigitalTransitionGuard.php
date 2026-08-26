<?php

namespace App\Actions\DocDigital;

use App\Contracts\Tramites\TramiteTransitionGuard;
use App\Models\Tramite;
use App\Models\TransicionEstado;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class DocDigitalTransitionGuard implements TramiteTransitionGuard
{
    public function validate(Tramite $tramite, TransicionEstado $transicion, User $user, ?string $observation, array $metadata): void
    {
        if (! in_array($transicion->codigo_accion, ['REGISTRAR_ENVIO_DOCDIGITAL', 'REGISTRAR_FORMALIZACION'], true)) {
            return;
        }
        $record = $tramite->registroDocDigitalActual()->with(['adjuntoEnviado', 'adjuntoFinal'])->first();
        if (! $record || $record->tramite_id !== $tramite->id) {
            throw ValidationException::withMessages(['docdigital' => 'Falta el registro DocDigital actual.']);
        }
        if ($transicion->codigo_accion === 'REGISTRAR_ENVIO_DOCDIGITAL' && ($record->estado !== 'ENVIADO' || ! $record->adjuntoEnviado || ! Storage::disk('private')->exists($record->adjuntoEnviado->storage_path))) {
            throw ValidationException::withMessages(['docdigital' => 'El envío DocDigital no tiene un documento existente válido.']);
        }
        if ($transicion->codigo_accion === 'REGISTRAR_FORMALIZACION' && ($record->estado !== 'FORMALIZADO' || ! $record->fecha_formalizacion || ! $record->formalizado_por || ! $record->adjuntoFinal || ! Storage::disk('private')->exists($record->adjuntoFinal->storage_path))) {
            throw ValidationException::withMessages(['docdigital' => 'La formalización debe incluir usuario, fecha y documento final existente.']);
        }
    }
}
