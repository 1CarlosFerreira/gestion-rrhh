<?php

namespace App\Actions\Reemplazos;

use App\Models\Tramite;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ActualizarGradoEusPendiente
{
    public function execute(Tramite $tramite, int $grado, User $user): Tramite
    {
        if (! $user->can('reemplazos.revisar_personal') || Gate::forUser($user)->denies('view', $tramite)) {
            throw new AuthorizationException;
        }

        return DB::transaction(function () use ($tramite, $grado, $user): Tramite {
            $locked = Tramite::query()->lockForUpdate()->findOrFail($tramite->id);
            $locked->load('estadoTramite');
            if ($locked->estadoTramite->codigo !== 'LISTA_GENERAR_DOCUMENTO') {
                throw new AuthorizationException('El grado solo puede corregirse después de una generación pendiente.');
            }
            $revision = $locked->revisionReemplazo()->firstOrFail();
            if ((int) $revision->grado_eus_informado !== $grado) {
                $revision->update(['grado_eus_informado' => $grado]);
                $locked->historial()->create([
                    'user_id' => $user->id,
                    'action_code' => 'GRADO_EUS_INFORMADO',
                    'metadata' => ['grado_eus_informado' => $grado],
                    'occurred_at' => now(),
                ]);
            }

            return $locked->refresh();
        });
    }
}
