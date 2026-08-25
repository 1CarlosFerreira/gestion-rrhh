<?php

namespace App\Actions\HorasExtraordinarias;

use App\Models\HorasExtraFuncionario;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class RegistrarHorasExtra
{
    public function execute(HorasExtraFuncionario $funcionario, int $dayHours, int $dayMinutes, int $nightHours, int $nightMinutes, User $user): HorasExtraFuncionario
    {
        if (! $user->can('horas_extra.registrar_horas')) {
            throw new AuthorizationException;
        }
        $funcionario->refresh()->load('tramiteHorasExtra.tramite.estadoTramite', 'planillaVigente');
        $tramite = $funcionario->tramiteHorasExtra->tramite->fresh(['estadoTramite']);
        if (Gate::forUser($user)->denies('view', $tramite)) {
            throw new AuthorizationException;
        }
        if (! $funcionario->planillaVigente || ($tramite->estadoTramite->codigo === 'EN_CORRECCION_SIRH' && $funcionario->review_status !== 'PENDIENTE') || ! in_array($tramite->estadoTramite->codigo, ['ENVIADA_GESTION_PERSONAS', 'EN_CORRECCION_SIRH'], true) || min($dayHours, $dayMinutes, $nightHours, $nightMinutes) < 0 || $dayMinutes > 59 || $nightMinutes > 59) {
            throw ValidationException::withMessages(['horas' => 'Las horas o minutos no son válidos para el estado actual.']);
        }
        $daytime = $dayHours * 60 + $dayMinutes;
        $night = $nightHours * 60 + $nightMinutes;
        $funcionario->update(['daytime_minutes' => $daytime, 'night_festive_minutes' => $night, 'total_minutes' => $daytime + $night]);
        $tramite->historial()->create(['user_id' => $user->id, 'action_code' => 'HORAS_EXTRA_REGISTRADAS', 'metadata' => ['participante_id' => $funcionario->id, 'total_minutes' => $daytime + $night], 'occurred_at' => now()]);

        return $funcionario->refresh();
    }
}
