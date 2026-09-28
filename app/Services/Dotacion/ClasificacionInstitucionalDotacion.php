<?php

namespace App\Services\Dotacion;

use App\Enums\EstadoVinculoDotacion;
use App\Enums\RolDotacion;
use App\Enums\TipoResponsabilidad;
use App\Models\PersonaUnidadVinculo;
use App\Models\UnidadResponsable;
use Illuminate\Support\Collection;

class ClasificacionInstitucionalDotacion
{
    public function resolver(Collection $vinculos, Collection $reemplazosPorVinculo, string $fecha): Collection
    {
        if ($vinculos->isEmpty()) {
            return collect();
        }

        $responsabilidades = UnidadResponsable::query()
            ->whereIn('unidad_organizacional_id', $vinculos->pluck('unidad_organizacional_id')->unique())
            ->whereIn('persona_id', $vinculos->pluck('persona_id')->unique())
            ->whereIn('tipo', [TipoResponsabilidad::TITULAR->value, TipoResponsabilidad::SUBROGANTE->value])
            ->vigentesEn($fecha)
            ->get(['unidad_organizacional_id', 'persona_id', 'tipo'])
            ->groupBy(fn (UnidadResponsable $responsabilidad): string => $this->clave(
                $responsabilidad->unidad_organizacional_id,
                $responsabilidad->persona_id,
            ));

        return $vinculos->mapWithKeys(function (PersonaUnidadVinculo $vinculo) use ($reemplazosPorVinculo, $responsabilidades, $fecha): array {
            if ($vinculo->estadoEn($fecha) === EstadoVinculoDotacion::VIGENTE && $reemplazosPorVinculo->has($vinculo->id)) {
                return [$vinculo->id => RolDotacion::REEMPLAZO];
            }

            $tipos = $responsabilidades
                ->get($this->clave($vinculo->unidad_organizacional_id, $vinculo->persona_id), collect())
                ->pluck('tipo');

            if ($tipos->contains(TipoResponsabilidad::SUBROGANTE)) {
                return [$vinculo->id => RolDotacion::SUBROGANTE];
            }

            if ($tipos->contains(TipoResponsabilidad::TITULAR)) {
                return [$vinculo->id => RolDotacion::JEFATURA_TITULAR];
            }

            return [$vinculo->id => RolDotacion::INTEGRANTE];
        });
    }

    private function clave(int $unidadId, int $personaId): string
    {
        return $unidadId.':'.$personaId;
    }
}
