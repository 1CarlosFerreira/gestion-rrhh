<?php

namespace App\Services\Dotacion;

use App\Models\PersonaUnidadVinculo;
use App\Models\TramiteReemplazo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ResumenReemplazosDotacion
{
    public function resolver(Collection $vinculos, string $fecha): array
    {
        $reemplazosPorVinculo = $vinculos
            ->filter(fn (PersonaUnidadVinculo $vinculo): bool => $this->esVinculoDeReemplazo($vinculo))
            ->mapWithKeys(fn (PersonaUnidadVinculo $vinculo): array => [
                $vinculo->id => $vinculo->tramiteOrigen->reemplazo,
            ]);

        $personaIds = $vinculos->pluck('persona_id')->unique()->values();
        $unidadIds = $vinculos->pluck('unidad_organizacional_id')->unique()->values();

        $coberturasActuales = TramiteReemplazo::query()
            ->with(['reemplazante', 'tramite.vinculoDotacion'])
            ->whereIn('funcionario_id', $personaIds)
            ->whereNotNull('reemplazante_id')
            ->whereDate('fecha_reemplazante_desde', '<=', $fecha)
            ->whereDate('fecha_reemplazante_hasta', '>=', $fecha)
            ->whereHas('tramite', fn (Builder $query): Builder => $query
                ->whereIn('unidad_organizacional_id', $unidadIds)
                ->whereHas('vinculoDotacion', fn (Builder $vinculo): Builder => $vinculo->vigentesEn($fecha)))
            ->get()
            ->groupBy(fn (TramiteReemplazo $reemplazo): string => $this->clave(
                $reemplazo->tramite->unidad_organizacional_id,
                $reemplazo->funcionario_id,
            ));

        return compact('reemplazosPorVinculo', 'coberturasActuales');
    }

    public function clave(int $unidadId, int $personaId): string
    {
        return $unidadId.':'.$personaId;
    }

    private function esVinculoDeReemplazo(PersonaUnidadVinculo $vinculo): bool
    {
        return $vinculo->esGeneradoPorTramite()
            && $vinculo->tramiteOrigen?->reemplazo?->reemplazante_id === $vinculo->persona_id;
    }
}
