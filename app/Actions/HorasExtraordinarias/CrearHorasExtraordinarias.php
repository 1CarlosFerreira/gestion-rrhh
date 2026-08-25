<?php

namespace App\Actions\HorasExtraordinarias;

use App\Actions\Tramites\CrearTramite;
use App\Models\TipoTramite;
use App\Models\Tramite;
use App\Models\UnidadServicio;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CrearHorasExtraordinarias
{
    public function __construct(private readonly CrearTramite $crearTramite) {}

    public function execute(UnidadServicio $unidad, int $year, int $month, array $personaIds, User $user): Tramite
    {
        if (! $user->can('horas_extra.crear')) {
            throw new AuthorizationException('No tiene permiso para crear solicitudes de horas extraordinarias.');
        }
        $ids = array_values(array_unique(array_map('intval', $personaIds)));
        if ($ids === [] || count($ids) !== count($personaIds)) {
            throw ValidationException::withMessages(['persona_ids' => 'Debe seleccionar funcionarios sin duplicados.']);
        }
        if ($month < 1 || $month > 12 || $year < 2000 || $year > 2100) {
            throw ValidationException::withMessages(['periodo' => 'El período no es válido.']);
        }
        if (DB::table('personas')->whereIn('id', $ids)->where('active', true)->count() !== count($ids)) {
            throw ValidationException::withMessages(['persona_ids' => 'Todos los funcionarios deben existir y estar activos.']);
        }

        return DB::transaction(function () use ($unidad, $year, $month, $ids, $user): Tramite {
            $tipo = TipoTramite::query()->where('codigo', 'HORAS_EXTRAORDINARIAS')->where('activo', true)->firstOrFail();
            $tramite = $this->crearTramite->execute($tipo, $unidad, $user);
            $start = CarbonImmutable::create($year, $month, 1)->startOfMonth();
            $detail = $tramite->horasExtra()->create([
                'year' => $year, 'month' => $month,
                'period_start' => $start->toDateString(), 'period_end' => $start->endOfMonth()->toDateString(),
            ]);
            foreach ($ids as $personaId) {
                $participant = $detail->funcionarios()->create(['persona_id' => $personaId]);
                $tramite->historial()->create(['user_id' => $user->id, 'action_code' => 'HORAS_EXTRA_FUNCIONARIO_AGREGADO', 'metadata' => ['participante_id' => $participant->id], 'occurred_at' => now()]);
            }

            return $tramite->refresh();
        });
    }
}
