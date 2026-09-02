<?php

namespace App\Actions\Reemplazos;

use App\Actions\Tramites\CrearTramite;
use App\Models\AusenciaReemplazable;
use App\Models\TipoTramite;
use App\Models\Tramite;
use App\Models\UnidadServicio;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CrearReemplazo
{
    public function __construct(
        private readonly CrearTramite $crearTramite,
        private readonly GuardarBorradorReemplazo $guardarBorrador,
    ) {}

    public function execute(UnidadServicio $unidad, User $user, array $data = []): Tramite
    {
        return DB::transaction(function () use ($unidad, $user, $data): Tramite {
            $tipo = TipoTramite::query()->where(['codigo' => 'REEMPLAZO', 'activo' => true])->firstOrFail();
            $tramite = $this->crearTramite->execute($tipo, $unidad, $user);
            $absence = AusenciaReemplazable::query()->create([
                'public_id' => (string) Str::ulid(),
                'unidad_servicio_id' => $unidad->id,
                'created_by' => $user->id,
            ]);
            $tramite->reemplazo()->create(['ausencia_reemplazable_id' => $absence->id]);

            $tramite->load(['estadoTramite', 'reemplazo']);

            return $data === [] ? $tramite : $this->guardarBorrador->execute($tramite, $data, $user);
        });
    }
}
