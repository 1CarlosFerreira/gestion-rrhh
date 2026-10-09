<?php

namespace App\Services\Respaldos;

use App\Models\RespaldoTransitorio;
use App\Models\SolicitudContrato;
use App\Models\TramiteReemplazo;
use Illuminate\Support\Collection;

class RespaldosAptosSolicitudTransitoria
{
    public function __construct(private readonly PeriodosInclusivos $periodos) {}

    /** @return Collection<int, RespaldoTransitorio> */
    public function obtener(SolicitudContrato $solicitud, TramiteReemplazo $detalle): Collection
    {
        if ($detalle->funcionario_id === null || $detalle->fecha_funcionario_desde === null || $detalle->fecha_funcionario_hasta === null) {
            return collect();
        }

        $desde = $detalle->fecha_funcionario_desde->toDateString();
        $hasta = $detalle->fecha_funcionario_hasta->toDateString();
        $this->periodos->validar($desde, $hasta);

        return $solicitud->respaldosRegistrados()
            ->with('versionActual')
            ->whereDoesntHave('afectaciones', fn ($query) => $query->whereNull('liberado_at'))
            ->get()
            ->filter(function ($respaldo) use ($solicitud, $detalle, $desde, $hasta): bool {
                $version = $respaldo->versionActual;

                return $version !== null
                    && $version->funcionario_origen_id === $detalle->funcionario_id
                    && $version->unidad_origen_id === $solicitud->unidad_origen_id
                    && $this->periodos->contiene($version->fecha_desde->toDateString(), $version->fecha_hasta->toDateString(), $desde, $hasta);
            })->values();
    }
}
