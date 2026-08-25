<?php

namespace App\Actions\HorasExtraordinarias;

use App\Models\Tramite;
use App\Models\UnidadServicio;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class GuardarBorradorHorasExtra
{
    public function execute(Tramite $tramite, UnidadServicio $unidad, int $year, int $month, array $personaIds, User $user): Tramite
    {
        if (! $user->can('horas_extra.crear') || Gate::forUser($user)->denies('view', $tramite)) {
            throw new AuthorizationException;
        }
        if ($tramite->tipoTramite()->value('codigo') !== 'HORAS_EXTRAORDINARIAS' || $tramite->estadoTramite()->value('codigo') !== 'BORRADOR') {
            throw ValidationException::withMessages(['tramite' => 'Solo se puede editar un borrador de Horas Extraordinarias.']);
        }
        if (! $unidad->activo || (! $user->can('tramites.ver_todos') && ! $user->unidadesHabilitadas()->whereKey($unidad->id)->exists())) {
            throw new AuthorizationException('La unidad no está habilitada para el usuario.');
        }
        $ids = array_values(array_unique(array_map('intval', $personaIds)));
        if (count($ids) !== count($personaIds)) {
            throw ValidationException::withMessages(['persona_ids' => 'No puede repetir funcionarios.']);
        }
        if ($month < 1 || $month > 12 || $year < 2000 || $year > 2100) {
            throw ValidationException::withMessages(['periodo' => 'El período no es válido.']);
        }

        return DB::transaction(function () use ($tramite, $unidad, $year, $month, $ids, $user): Tramite {
            $detail = $tramite->horasExtra()->lockForUpdate()->firstOrFail();
            $start = CarbonImmutable::create($year, $month, 1)->startOfMonth();
            $tramite->update(['unidad_servicio_id' => $unidad->id]);
            $detail->update(['year' => $year, 'month' => $month, 'period_start' => $start, 'period_end' => $start->endOfMonth()]);
            $existing = $detail->funcionarios()->pluck('id', 'persona_id');
            foreach ($existing->keys()->diff($ids) as $personaId) {
                $participantId = $existing[$personaId];
                $detail->funcionarios()->whereKey($participantId)->delete();
                $tramite->historial()->create(['user_id' => $user->id, 'action_code' => 'HORAS_EXTRA_FUNCIONARIO_QUITADO', 'metadata' => ['participante_id' => $participantId], 'occurred_at' => now()]);
            }
            foreach (collect($ids)->diff($existing->keys()) as $personaId) {
                $participant = $detail->funcionarios()->create(['persona_id' => $personaId]);
                $tramite->historial()->create(['user_id' => $user->id, 'action_code' => 'HORAS_EXTRA_FUNCIONARIO_AGREGADO', 'metadata' => ['participante_id' => $participant->id], 'occurred_at' => now()]);
            }

            return $tramite->refresh();
        });
    }
}
