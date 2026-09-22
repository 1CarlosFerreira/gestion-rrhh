<?php

namespace App\Actions\Dotacion;

use App\Enums\TipoResponsabilidad;
use App\Models\User;
use App\Services\Dotacion\DotacionService;
use App\Services\Responsabilidades\ResponsabilidadInstitucionalService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

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
}
