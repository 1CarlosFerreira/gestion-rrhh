<?php

namespace App\Actions\Reemplazos;

use App\Actions\Tramites\CrearTramite;
use App\Models\AusenciaReemplazable;
use App\Models\TipoTramite;
use App\Models\Tramite;
use App\Models\User;
use App\Services\Reemplazos\CoberturaAusenciaService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AgregarCoberturaAusencia
{
    public function __construct(private readonly CrearTramite $crearTramite, private readonly CoberturaAusenciaService $coberturas) {}

    public function execute(AusenciaReemplazable $absence, User $user): Tramite
    {
        if (! $user->can('reemplazos.crear') || (! $user->can('tramites.ver_todos') && ! $user->unidadesHabilitadas()->whereKey($absence->unidad_servicio_id)->exists())) {
            throw new AuthorizationException;
        }

        return DB::transaction(function () use ($absence, $user): Tramite {
            $locked = AusenciaReemplazable::query()->lockForUpdate()->findOrFail($absence->id);
            if ($locked->closed_at) {
                throw ValidationException::withMessages(['ausencia' => 'La ausencia está cerrada y no admite nuevas coberturas.']);
            }
            if ($this->coberturas->summary($locked)['dias_disponibles'] === 0) {
                throw ValidationException::withMessages(['ausencia' => 'No quedan días disponibles para una nueva cobertura.']);
            }
            $type = TipoTramite::query()->where(['codigo' => 'REEMPLAZO', 'activo' => true])->firstOrFail();
            $tramite = $this->crearTramite->execute($type, $locked->unidad, $user);
            $first = $locked->coberturas()->orderBy('id')->first();
            $tramite->reemplazo()->create([
                'ausencia_reemplazable_id' => $locked->id,
                'tipo_reemplazo_id' => $locked->tipo_reemplazo_id,
                'funcionario_id' => $locked->funcionario_id,
                'funcionario_vinculo_id' => $first?->funcionario_vinculo_id,
                'justificacion' => $locked->justificacion,
            ]);
            $locked->historial()->create([
                'user_id' => $user->id,
                'action_code' => 'COBERTURA_AGREGADA',
                'metadata' => ['tramite_id' => $tramite->id],
                'occurred_at' => now(),
            ]);

            return $tramite->load(['estadoTramite', 'reemplazo']);
        });
    }
}
