<?php

namespace App\Actions\Tramites;

use App\Contracts\Tramites\TramiteTransitionGuard;
use App\Models\Tramite;
use App\Models\TransicionEstado;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransicionarTramite
{
    public function execute(Tramite $tramite, string $actionCode, User $user, ?string $observation = null, array $metadata = []): Tramite
    {
        return DB::transaction(function () use ($tramite, $actionCode, $user, $observation, $metadata): Tramite {
            $locked = Tramite::query()->lockForUpdate()->findOrFail($tramite->id);
            $transition = TransicionEstado::query()
                ->with(['estadoOrigen', 'estadoDestino'])
                ->where('tipo_tramite_id', $locked->tipo_tramite_id)
                ->where('estado_origen_id', $locked->estado_tramite_id)
                ->where('codigo_accion', $actionCode)
                ->where('activo', true)
                ->first();

            if (! $transition) {
                throw ValidationException::withMessages(['action_code' => 'La transición no está permitida desde el estado actual.']);
            }
            if ($transition->estadoOrigen->tipo_tramite_id !== $locked->tipo_tramite_id || $transition->estadoDestino->tipo_tramite_id !== $locked->tipo_tramite_id) {
                throw ValidationException::withMessages(['action_code' => 'Los estados de la transición no pertenecen al tipo del trámite.']);
            }
            if ($transition->permiso_requerido && ! $user->can($transition->permiso_requerido)) {
                throw new AuthorizationException('No tiene el permiso requerido para esta transición.');
            }
            if ($transition->requiere_observacion && blank($observation)) {
                throw ValidationException::withMessages(['observation' => 'La observación es obligatoria para esta transición.']);
            }

            foreach (app()->tagged('tramite.transition.guards') as $guard) {
                assert($guard instanceof TramiteTransitionGuard);
                $guard->validate($locked, $transition, $user, $observation, $metadata);
            }

            $fromState = $locked->estado_tramite_id;
            $locked->estado_tramite_id = $transition->estado_destino_id;
            if ($locked->submitted_at === null && $transition->estadoDestino->codigo === 'ENVIADA_GESTION_PERSONAS') {
                $locked->submitted_at = now();
            }
            if ($transition->estadoDestino->codigo === 'FORMALIZADA') {
                $locked->finalized_at = now();
            }
            $locked->save();

            $locked->historial()->create([
                'user_id' => $user->id,
                'action_code' => $transition->codigo_accion,
                'from_estado_id' => $fromState,
                'to_estado_id' => $transition->estado_destino_id,
                'observation' => filled($observation) ? trim($observation) : null,
                'metadata' => $metadata ?: null,
                'occurred_at' => now(),
            ]);

            return $locked->refresh();
        });
    }
}
