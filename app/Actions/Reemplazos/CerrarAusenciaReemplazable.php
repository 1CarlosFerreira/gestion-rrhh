<?php

namespace App\Actions\Reemplazos;

use App\Models\AusenciaReemplazable;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CerrarAusenciaReemplazable
{
    public function execute(AusenciaReemplazable $absence, string $reason, User $user): AusenciaReemplazable
    {
        if (! $user->can('reemplazos.crear') || (! $user->can('tramites.ver_todos') && ! $user->unidadesHabilitadas()->whereKey($absence->unidad_servicio_id)->exists())) {
            throw new AuthorizationException;
        }
        if (blank($reason)) {
            throw ValidationException::withMessages(['motivo' => 'Debe indicar un motivo para cerrar la ausencia.']);
        }

        return DB::transaction(function () use ($absence, $reason, $user): AusenciaReemplazable {
            $locked = AusenciaReemplazable::query()->lockForUpdate()->findOrFail($absence->id);
            if (! $locked->closed_at) {
                $locked->update(['closed_at' => now(), 'closed_by' => $user->id, 'close_reason' => $reason]);
                $locked->historial()->create(['user_id' => $user->id, 'action_code' => 'AUSENCIA_CERRADA', 'observation' => $reason, 'occurred_at' => now()]);
            }

            return $locked->refresh();
        });
    }
}
