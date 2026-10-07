<?php

namespace App\Actions\Tramites\Adjuntos;

use App\Models\Tramite;
use App\Models\TramiteAdjunto;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class AnularAdjunto
{
    public function execute(Tramite $tramite, TramiteAdjunto $adjunto, User $user): TramiteAdjunto
    {
        return DB::transaction(function () use ($tramite, $adjunto, $user): TramiteAdjunto {
            $lockedTramite = Tramite::query()->lockForUpdate()->findOrFail($tramite->id);
            $locked = TramiteAdjunto::query()->lockForUpdate()->findOrFail($adjunto->id);
            if ($locked->tramite_id !== $lockedTramite->id
                || ! $user->can('tramites.adjuntos.anular')
                || Gate::forUser($user)->denies('view', $lockedTramite)) {
                throw new AuthorizationException('No está autorizado para anular el adjunto.');
            }
            if ($locked->documentoGenerado()->exists()) {
                throw ValidationException::withMessages([
                    'archivo' => 'Un documento generado no puede anularse mediante el flujo general de adjuntos.',
                ]);
            }
            $locked->update(['status' => 'ANULADO']);
            $lockedTramite->historial()->create(['user_id' => $user->id, 'action_code' => 'ADJUNTO_ANULADO', 'metadata' => ['adjunto_id' => $locked->id, 'tipo_documento_id' => $locked->tipo_documento_id, 'version' => $locked->version], 'occurred_at' => now()]);

            return $locked->refresh();
        });
    }
}
