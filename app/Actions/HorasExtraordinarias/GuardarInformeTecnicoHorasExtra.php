<?php

namespace App\Actions\HorasExtraordinarias;

use App\Models\HorasExtraInformeTecnico;
use App\Models\Tramite;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class GuardarInformeTecnicoHorasExtra
{
    public function execute(Tramite $tramite, array $data, User $user): HorasExtraInformeTecnico
    {
        if (! $user->can('documentos.generar') || Gate::forUser($user)->denies('view', $tramite)) {
            throw new AuthorizationException;
        }
        if ($tramite->tipoTramite()->value('codigo') !== 'HORAS_EXTRAORDINARIAS' || ! in_array($tramite->estadoTramite()->value('codigo'), ['CONFORME', 'INFORME_TECNICO_GENERADO'], true)) {
            throw ValidationException::withMessages(['tramite' => 'El Informe Técnico solo puede prepararse para Horas Extraordinarias conformes.']);
        }
        if (! ($data['horario_diurno'] ?? false) && ! ($data['horario_festivo'] ?? false)) {
            throw ValidationException::withMessages(['horario_diurno' => 'Debe seleccionar al menos un horario.']);
        }
        if (! ($data['retribucion_tiempo'] ?? false) && ! ($data['retribucion_dinero'] ?? false)) {
            throw ValidationException::withMessages(['retribucion_tiempo' => 'Debe seleccionar al menos una forma de retribución.']);
        }
        if (blank($data['justificacion_tecnica'] ?? null) || blank($data['medidas_control'] ?? null)) {
            throw ValidationException::withMessages(['informe' => 'Justificación técnica y medidas de control son obligatorias.']);
        }

        return $tramite->horasExtra()->firstOrFail()->informeTecnico()->updateOrCreate([], [
            'horario_diurno' => (bool) $data['horario_diurno'],
            'horario_festivo' => (bool) $data['horario_festivo'],
            'retribucion_tiempo' => (bool) $data['retribucion_tiempo'],
            'retribucion_dinero' => (bool) $data['retribucion_dinero'],
            'justificacion_tecnica' => trim($data['justificacion_tecnica']),
            'medidas_control' => trim($data['medidas_control']),
            'prepared_by' => $user->id,
            'prepared_at' => now(),
        ]);
    }
}
