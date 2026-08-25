<?php

namespace App\Actions\Reemplazos;

use App\Actions\Tramites\CrearTramite;
use App\Models\TipoTramite;
use App\Models\Tramite;
use App\Models\UnidadServicio;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CrearReemplazo
{
    public function __construct(private readonly CrearTramite $crearTramite) {}

    public function execute(UnidadServicio $unidad, User $user): Tramite
    {
        return DB::transaction(function () use ($unidad, $user): Tramite {
            $tipo = TipoTramite::query()->where(['codigo' => 'REEMPLAZO', 'activo' => true])->firstOrFail();
            $tramite = $this->crearTramite->execute($tipo, $unidad, $user);
            $tramite->reemplazo()->create();

            return $tramite->load('reemplazo');
        });
    }
}
