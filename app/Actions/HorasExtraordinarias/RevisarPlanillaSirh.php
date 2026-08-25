<?php

namespace App\Actions\HorasExtraordinarias;

use App\Models\HorasExtraFuncionario;
use App\Models\HorasExtraRevision;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class RevisarPlanillaSirh
{
    public function execute(HorasExtraFuncionario $funcionario, string $result, ?string $observation, User $user): HorasExtraRevision
    {
        if (! $user->can('horas_extra.revisar_planilla')) {
            throw new AuthorizationException;
        }
        $funcionario->loadMissing('tramiteHorasExtra.tramite.estadoTramite', 'planillaVigente');
        $tramite = $funcionario->tramiteHorasExtra->tramite;
        if (Gate::forUser($user)->denies('view', $tramite)) {
            throw new AuthorizationException;
        }
        if ($tramite->estadoTramite->codigo !== 'EN_REVISION_JEFATURA' || $funcionario->review_status !== 'PENDIENTE' || ! in_array($result, ['CONFORME', 'OBSERVADA'], true)) {
            throw ValidationException::withMessages(['result' => 'La decisión no es válida en el estado actual.']);
        }
        if ($result === 'OBSERVADA' && blank($observation)) {
            throw ValidationException::withMessages(['observation' => 'La observación es obligatoria.']);
        }
        if (! $funcionario->planillaVigente) {
            throw ValidationException::withMessages(['planilla' => 'El funcionario no tiene una planilla vigente.']);
        }

        return DB::transaction(function () use ($funcionario, $result, $observation, $user, $tramite): HorasExtraRevision {
            $revision = HorasExtraRevision::query()->create([
                'planilla_sirh_id' => $funcionario->planillaVigente->id, 'result' => $result,
                'observation' => filled($observation) ? trim($observation) : null,
                'reviewed_by' => $user->id, 'reviewed_at' => now(),
            ]);
            $funcionario->update(['review_status' => $result]);
            $tramite->historial()->create([
                'user_id' => $user->id, 'action_code' => 'PLANILLA_REVISADA_'.$result,
                'observation' => $revision->observation, 'metadata' => ['participante_id' => $funcionario->id, 'planilla_id' => $revision->planilla_sirh_id], 'occurred_at' => now(),
            ]);

            return $revision;
        });
    }
}
