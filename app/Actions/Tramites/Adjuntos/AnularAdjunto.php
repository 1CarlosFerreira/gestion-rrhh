<?php

namespace App\Actions\Tramites\Adjuntos;

use App\Models\Tramite;
use App\Models\TramiteAdjunto;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class AnularAdjunto
{
    public function execute(Tramite $tramite, TramiteAdjunto $adjunto, User $user): TramiteAdjunto
    {
        if ($adjunto->tramite_id !== $tramite->id || ! $user->can('tramites.adjuntos.anular') || Gate::forUser($user)->denies('view', $tramite)) {
            throw new AuthorizationException('No está autorizado para anular el adjunto.');
        }

        return DB::transaction(function () use ($tramite, $adjunto, $user): TramiteAdjunto {
            $locked = TramiteAdjunto::query()->lockForUpdate()->findOrFail($adjunto->id);
            $locked->update(['status' => 'ANULADO']);
            $tramite->historial()->create(['user_id' => $user->id, 'action_code' => 'ADJUNTO_ANULADO', 'metadata' => ['adjunto_id' => $locked->id, 'tipo_documento_id' => $locked->tipo_documento_id, 'version' => $locked->version], 'occurred_at' => now()]);

            return $locked->refresh();
        });
    }
}
