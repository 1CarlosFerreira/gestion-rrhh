<?php

namespace App\Services\Respaldos;

use App\Enums\ModalidadSolicitudContrato;
use App\Models\ReservaPersonaPeriodo;
use App\Models\RespaldoAfectacion;
use App\Models\RespaldoTransitorio;
use App\Models\RespaldoTransitorioVersion;
use App\Models\SolicitudContrato;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;

class EscriturasPeriodosTransitorios
{
    public function __construct(
        private readonly PeriodosInclusivos $periodos,
        private readonly ConflictosPeriodosTransitorios $conflictos,
        private readonly TransaccionPeriodosTransitorios $transaccion,
    ) {}

    public function registrarRespaldo(SolicitudContrato $solicitud, User $actor, array $datos): RespaldoTransitorio
    {
        $this->periodos->validar($datos['fecha_desde'], $datos['fecha_hasta']);

        return $this->transaccion->ejecutar([$datos['funcionario_origen_id']], function () use ($solicitud, $actor, $datos): RespaldoTransitorio {
            $solicitud = SolicitudContrato::query()->lockForUpdate()->findOrFail($solicitud->id);
            if ($solicitud->modalidad !== ModalidadSolicitudContrato::TRANSITORIA || $solicitud->unidad_origen_id !== (int) $datos['unidad_origen_id']) {
                throw ValidationException::withMessages(['unidad_origen_id' => 'El respaldo requiere una solicitud transitoria y su unidad origen.']);
            }
            $this->validarRespaldoLibre((int) $datos['funcionario_origen_id'], $datos['fecha_desde'], $datos['fecha_hasta']);

            $respaldo = RespaldoTransitorio::query()->create(['solicitud_origen_id' => $solicitud->id, 'created_by' => $actor->id]);
            $respaldo->versiones()->create([
                'version' => 1,
                'funcionario_origen_id' => $datos['funcionario_origen_id'],
                'unidad_origen_id' => $datos['unidad_origen_id'],
                'motivo' => $datos['motivo'],
                'fecha_desde' => $datos['fecha_desde'],
                'fecha_hasta' => $datos['fecha_hasta'],
                'referencia_externa' => $datos['referencia_externa'] ?? null,
                'registrado_por' => $actor->id,
            ]);

            return $respaldo->load('versionActual');
        });
    }

    public function rectificarPeriodoRespaldo(RespaldoTransitorio $respaldo, User $actor, string $desde, string $hasta, string $motivo): RespaldoTransitorioVersion
    {
        $this->periodos->validar($desde, $hasta);
        if (blank($motivo)) {
            throw ValidationException::withMessages(['motivo_rectificacion' => 'La rectificación requiere un motivo.']);
        }

        $versionPrevista = $respaldo->versionActual()->firstOrFail();

        return $this->transaccion->ejecutar([$versionPrevista->funcionario_origen_id], function () use ($respaldo, $actor, $desde, $hasta, $motivo, $versionPrevista): RespaldoTransitorioVersion {
            RespaldoTransitorio::query()->lockForUpdate()->findOrFail($respaldo->id);
            $actual = RespaldoTransitorioVersion::query()->where('respaldo_id', $respaldo->id)->orderByDesc('version')->lockForUpdate()->firstOrFail();
            if ($actual->funcionario_origen_id !== $versionPrevista->funcionario_origen_id) {
                throw ValidationException::withMessages(['funcionario_origen_id' => 'El funcionario origen cambió; reintente la rectificación.']);
            }
            if (RespaldoAfectacion::query()->where('respaldo_id', $respaldo->id)->whereNull('liberado_at')->exists()) {
                throw ValidationException::withMessages(['respaldo' => 'Un respaldo comprometido requiere rectificación coordinada con cobertura y reservas.']);
            }
            $this->validarRespaldoLibre($actual->funcionario_origen_id, $desde, $hasta, $respaldo->id);

            return $respaldo->versiones()->create([
                'version' => $actual->version + 1,
                'funcionario_origen_id' => $actual->funcionario_origen_id,
                'unidad_origen_id' => $actual->unidad_origen_id,
                'motivo' => $actual->motivo,
                'fecha_desde' => $desde,
                'fecha_hasta' => $hasta,
                'referencia_externa' => $actual->referencia_externa,
                'registrado_por' => $actor->id,
                'motivo_rectificacion' => $motivo,
            ]);
        });
    }

    public function registrarReserva(RespaldoAfectacion $afectacion, User $actor, int $personaId, string $desde, string $hasta): ReservaPersonaPeriodo
    {
        $this->periodos->validar($desde, $hasta);

        try {
            return $this->transaccion->ejecutar([$personaId], function () use ($afectacion, $actor, $personaId, $desde, $hasta): ReservaPersonaPeriodo {
                $idRespaldo = RespaldoAfectacion::query()->whereKey($afectacion->id)->value('respaldo_id');
                RespaldoTransitorio::query()->lockForUpdate()->findOrFail($idRespaldo);
                $afectacion = RespaldoAfectacion::query()->lockForUpdate()->findOrFail($afectacion->id);
                if ($afectacion->liberado_at !== null) {
                    throw ValidationException::withMessages(['afectacion' => 'La afectación ya fue liberada.']);
                }
                $version = $afectacion->versionRespaldo;
                if (! $this->periodos->contiene($version->fecha_desde->toDateString(), $version->fecha_hasta->toDateString(), $desde, $hasta)) {
                    throw ValidationException::withMessages(['fecha_desde' => 'La reserva debe quedar contenida en el período del respaldo.']);
                }
                if ($this->conflictos->reservaBajoBloqueo($personaId, $desde, $hasta)) {
                    throw ValidationException::withMessages(['fecha_desde' => 'La persona ya posee una reserva vigente superpuesta.']);
                }

                return ReservaPersonaPeriodo::query()->create([
                    'afectacion_id' => $afectacion->id,
                    'persona_id' => $personaId,
                    'fecha_desde' => $desde,
                    'fecha_hasta' => $hasta,
                    'reservado_at' => now(),
                    'reservado_por' => $actor->id,
                ]);
            });
        } catch (QueryException $exception) {
            if ($this->esConflictoPersistente($exception)) {
                throw ValidationException::withMessages(['reserva' => 'La disponibilidad cambió durante la reserva; reintente la operación.']);
            }

            throw $exception;
        }
    }

    private function validarRespaldoLibre(int $funcionarioId, string $desde, string $hasta, ?int $excluirRespaldoId = null): void
    {
        if ($this->conflictos->respaldoBajoBloqueo($funcionarioId, $desde, $hasta, $excluirRespaldoId)) {
            throw ValidationException::withMessages(['fecha_desde' => 'El funcionario origen ya tiene un respaldo superpuesto.']);
        }
    }

    private function esConflictoPersistente(QueryException $exception): bool
    {
        return in_array((string) $exception->getCode(), ['23000', '40001', '41000'], true)
            || in_array((int) ($exception->errorInfo[1] ?? 0), [1062, 1205, 1213], true);
    }
}
