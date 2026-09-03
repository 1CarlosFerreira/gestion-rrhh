<?php

namespace App\Services\Reemplazos;

use Illuminate\Database\Eloquent\Builder;

class ReemplazoWorkflow
{
    public const ESTADOS_ACTIVOS = ['BORRADOR', 'ENVIADA_GESTION_PERSONAS', 'EN_REVISION', 'DEVUELTA_PARA_CORRECCION', 'LISTA_GENERAR_DOCUMENTO'];

    public function filtrarActivos(Builder $query): void
    {
        $query->whereHas('estadoTramite', fn (Builder $estado) => $estado->whereIn('codigo', self::ESTADOS_ACTIVOS));
    }
}
