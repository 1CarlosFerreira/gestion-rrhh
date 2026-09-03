<?php

namespace App\Services\Reemplazos;

use App\Models\TipoReemplazo;
use App\Models\Tramite;
use App\Models\TramiteReemplazo;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReemplazoService
{
    public function crear(Tramite $tramite, array $datos, ?Closure $filtrarTramitesActivos = null): TramiteReemplazo
    {
        return DB::transaction(function () use ($tramite, $datos, $filtrarTramitesActivos): TramiteReemplazo {
            Tramite::query()->lockForUpdate()->findOrFail($tramite->id);
            if ($tramite->reemplazo()->exists()) {
                throw ValidationException::withMessages(['tramite_id' => 'El trámite ya posee su único detalle de reemplazo.']);
            }
            $this->validar($datos);
            $this->validarTipo($datos['tipo_reemplazo_id']);
            $this->validarSuperposicion($datos['funcionario_id'], $datos['fecha_funcionario_desde'], $datos['fecha_funcionario_hasta'], null, $filtrarTramitesActivos);

            return $tramite->reemplazo()->create($datos);
        });
    }

    public function actualizar(TramiteReemplazo $reemplazo, array $datos, ?Closure $filtrarTramitesActivos = null): TramiteReemplazo
    {
        return DB::transaction(function () use ($reemplazo, $datos, $filtrarTramitesActivos): TramiteReemplazo {
            $reemplazo = TramiteReemplazo::query()->lockForUpdate()->findOrFail($reemplazo->id);
            $combinados = [...$reemplazo->getAttributes(), ...$datos];
            $this->validar($combinados);
            $this->validarTipo($combinados['tipo_reemplazo_id']);
            $this->validarSuperposicion($combinados['funcionario_id'], $combinados['fecha_funcionario_desde'], $combinados['fecha_funcionario_hasta'], $reemplazo->id, $filtrarTramitesActivos);
            $reemplazo->update($datos);

            return $reemplazo->refresh();
        });
    }

    public function existeSuperposicion(int $funcionarioId, string|\DateTimeInterface $desde, string|\DateTimeInterface $hasta, ?int $exceptoReemplazoId = null, ?Closure $filtrarTramitesActivos = null): bool
    {
        $desde = CarbonImmutable::parse($desde)->toDateString();
        $hasta = CarbonImmutable::parse($hasta)->toDateString();

        return TramiteReemplazo::query()
            ->where('funcionario_id', $funcionarioId)
            ->when($exceptoReemplazoId, fn (Builder $query) => $query->whereKeyNot($exceptoReemplazoId))
            ->when($filtrarTramitesActivos, fn (Builder $query) => $query->whereHas('tramite', $filtrarTramitesActivos))
            ->whereDate('fecha_funcionario_desde', '<=', $hasta)
            ->whereDate('fecha_funcionario_hasta', '>=', $desde)
            ->exists();
    }

    public function validar(array $datos): void
    {
        $errores = [];
        $funcionarioDesde = $this->fecha($datos, 'fecha_funcionario_desde', $errores);
        $funcionarioHasta = $this->fecha($datos, 'fecha_funcionario_hasta', $errores);
        $reemplazanteDesde = $this->fechaOpcional($datos, 'fecha_reemplazante_desde', $errores);
        $reemplazanteHasta = $this->fechaOpcional($datos, 'fecha_reemplazante_hasta', $errores);

        if ($funcionarioDesde && $funcionarioHasta && $funcionarioDesde->gt($funcionarioHasta)) {
            $errores['fecha_funcionario_hasta'] = 'El término del funcionario debe ser igual o posterior al inicio.';
        }
        if (($reemplazanteDesde === null) xor ($reemplazanteHasta === null)) {
            $errores['fecha_reemplazante_desde'] = 'El período del reemplazante debe informar ambas fechas.';
        }
        if (($datos['reemplazante_id'] ?? null) === null && ($reemplazanteDesde !== null || $reemplazanteHasta !== null)) {
            $errores['reemplazante_id'] = 'Debe identificar al reemplazante para informar su período.';
        }
        if (($datos['reemplazante_id'] ?? null) !== null && ($reemplazanteDesde === null || $reemplazanteHasta === null)) {
            $errores['fecha_reemplazante_desde'] = 'El reemplazante debe tener un período efectivo completo.';
        }
        if ($reemplazanteDesde && $reemplazanteHasta) {
            if ($reemplazanteDesde->gt($reemplazanteHasta)) {
                $errores['fecha_reemplazante_hasta'] = 'El término del reemplazante debe ser igual o posterior al inicio.';
            }
            if ($funcionarioDesde && $reemplazanteDesde->lt($funcionarioDesde)) {
                $errores['fecha_reemplazante_desde'] = 'La cobertura no puede comenzar antes del período del funcionario.';
            }
            if ($funcionarioHasta && $reemplazanteHasta->gt($funcionarioHasta)) {
                $errores['fecha_reemplazante_hasta'] = 'La cobertura no puede terminar después del período del funcionario.';
            }
        }
        if (($datos['reemplazante_id'] ?? null) !== null && (int) $datos['funcionario_id'] === (int) $datos['reemplazante_id']) {
            $errores['reemplazante_id'] = 'El reemplazante debe ser una persona distinta del funcionario.';
        }
        if ($errores !== []) {
            throw ValidationException::withMessages($errores);
        }
    }

    private function validarSuperposicion(int $funcionarioId, string|\DateTimeInterface $desde, string|\DateTimeInterface $hasta, ?int $exceptoId, ?Closure $filtro): void
    {
        if ($this->existeSuperposicion($funcionarioId, $desde, $hasta, $exceptoId, $filtro)) {
            throw ValidationException::withMessages(['fecha_funcionario_desde' => 'El funcionario ya posee otro reemplazo con un período superpuesto.']);
        }
    }

    private function validarTipo(int $tipoId): void
    {
        if (! TipoReemplazo::query()->whereKey($tipoId)->where('activo', true)->exists()) {
            throw ValidationException::withMessages(['tipo_reemplazo_id' => 'El tipo de reemplazo debe estar activo.']);
        }
    }

    private function fecha(array $datos, string $campo, array &$errores): ?CarbonImmutable
    {
        if (empty($datos[$campo])) {
            $errores[$campo] = 'La fecha es obligatoria.';

            return null;
        }

        return $this->convertirFecha($datos[$campo], $campo, $errores);
    }

    private function fechaOpcional(array $datos, string $campo, array &$errores): ?CarbonImmutable
    {
        return empty($datos[$campo]) ? null : $this->convertirFecha($datos[$campo], $campo, $errores);
    }

    private function convertirFecha(mixed $valor, string $campo, array &$errores): ?CarbonImmutable
    {
        try {
            return CarbonImmutable::parse($valor)->startOfDay();
        } catch (\Throwable) {
            $errores[$campo] = 'La fecha no es válida.';

            return null;
        }
    }
}
