<?php

namespace App\Actions\Dotacion;

use App\Enums\TipoResponsabilidad;
use App\Models\User;
use App\Services\Dotacion\DotacionService;
use App\Services\Responsabilidades\ResponsabilidadInstitucionalService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CrearVinculoManualDotacion
{
    public function __construct(
        private readonly DotacionService $dotacion,
        private readonly ResponsabilidadInstitucionalService $responsabilidades,
    ) {}

    public function execute(array $datos, User $actor): ResultadoAltaDotacionManual
    {
        return DB::transaction(function () use ($datos, $actor): ResultadoAltaDotacionManual {
            $tipo = $datos['responsabilidad_tipo'] ?? 'FUNCIONARIO';
            $vinculo = $this->dotacion->crear(Arr::except($datos, [
                'responsabilidad_tipo',
                'responsabilidad_desde',
                'responsabilidad_hasta',
            ]), $actor);

            if ($tipo === 'FUNCIONARIO') {
                return new ResultadoAltaDotacionManual($vinculo, null);
            }

            $this->validarVigenciaResponsabilidad($datos);
            $responsabilidad = $this->responsabilidades->crear([
                'unidad_organizacional_id' => $vinculo->unidad_organizacional_id,
                'persona_id' => $vinculo->persona_id,
                'tipo' => TipoResponsabilidad::from($tipo)->value,
                'vigente_desde' => $datos['responsabilidad_desde'],
                'vigente_hasta' => $datos['responsabilidad_hasta'] ?? null,
                'puede_aprobar' => true,
                'observacion' => null,
            ], $actor);

            return new ResultadoAltaDotacionManual($vinculo, $responsabilidad);
        });
    }

    private function validarVigenciaResponsabilidad(array $datos): void
    {
        $vinculoDesde = CarbonImmutable::parse($datos['vigente_desde']);
        $responsabilidadDesde = CarbonImmutable::parse($datos['responsabilidad_desde']);

        if ($responsabilidadDesde->lt($vinculoDesde)) {
            throw ValidationException::withMessages([
                'responsabilidad_desde' => 'La responsabilidad no puede comenzar antes que el vínculo laboral.',
            ]);
        }

        if (! empty($datos['vigente_hasta'])
            && ! empty($datos['responsabilidad_hasta'])
            && CarbonImmutable::parse($datos['responsabilidad_hasta'])->gt(CarbonImmutable::parse($datos['vigente_hasta']))) {
            throw ValidationException::withMessages([
                'responsabilidad_hasta' => 'La responsabilidad no puede terminar después que el vínculo laboral.',
            ]);
        }
    }
}
