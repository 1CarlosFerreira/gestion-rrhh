<?php

namespace App\Services\Documentos;

use App\Models\Persona;
use App\Models\Tramite;

class SnapshotRemitenteReemplazo
{
    public function crear(Tramite $tramite): array
    {
        $creador = $tramite->creador;
        $vinculo = filled($creador->rut)
            ? Persona::query()->where('rut', $creador->rut)->first()?->vinculosOperativos()->where('unidad_servicio_id', $tramite->unidad_servicio_id)->first()
            : null;

        return [
            'nombre' => $creador->name,
            'cargo' => $vinculo?->cargo_texto,
            'roles' => $creador->getRoleNames()->values()->all(),
            'unidad' => $tramite->unidadServicio->nombre,
            'capturado_at' => now()->toIso8601String(),
        ];
    }
}
