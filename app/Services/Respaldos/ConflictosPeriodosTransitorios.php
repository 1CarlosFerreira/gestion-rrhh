<?php

namespace App\Services\Respaldos;

use App\Models\ReservaPersonaPeriodo;
use Illuminate\Support\Facades\DB;
use LogicException;

class ConflictosPeriodosTransitorios
{
    public function __construct(private readonly PeriodosInclusivos $periodos) {}

    public function respaldo(int $funcionarioId, string $desde, string $hasta, ?int $excluirRespaldoId = null): bool
    {
        return $this->buscarRespaldo($funcionarioId, $desde, $hasta, $excluirRespaldoId, false);
    }

    public function respaldoBajoBloqueo(int $funcionarioId, string $desde, string $hasta, ?int $excluirRespaldoId = null): bool
    {
        $this->exigirTransaccion();

        return $this->buscarRespaldo($funcionarioId, $desde, $hasta, $excluirRespaldoId, true);
    }

    public function reserva(int $personaId, string $desde, string $hasta, ?int $excluirReservaId = null): bool
    {
        return $this->buscarReserva($personaId, $desde, $hasta, $excluirReservaId, false);
    }

    public function reservaBajoBloqueo(int $personaId, string $desde, string $hasta, ?int $excluirReservaId = null): bool
    {
        $this->exigirTransaccion();

        return $this->buscarReserva($personaId, $desde, $hasta, $excluirReservaId, true);
    }

    private function buscarRespaldo(int $funcionarioId, string $desde, string $hasta, ?int $excluirRespaldoId, bool $bloquear): bool
    {
        $this->periodos->validar($desde, $hasta);

        $consulta = DB::table('respaldo_transitorio_versiones as actual')
            ->where('actual.funcionario_origen_id', $funcionarioId)
            ->whereDate('actual.fecha_desde', '<=', $hasta)
            ->whereDate('actual.fecha_hasta', '>=', $desde)
            ->when($excluirRespaldoId !== null, fn ($query) => $query->where('actual.respaldo_id', '!=', $excluirRespaldoId))
            ->whereNotExists(fn ($query) => $query->selectRaw('1')
                ->from('respaldo_transitorio_versiones as posterior')
                ->whereColumn('posterior.respaldo_id', 'actual.respaldo_id')
                ->whereColumn('posterior.version', '>', 'actual.version'));

        if ($bloquear) {
            $consulta->lockForUpdate();
        }

        return $consulta->first(['actual.id']) !== null;
    }

    private function buscarReserva(int $personaId, string $desde, string $hasta, ?int $excluirReservaId, bool $bloquear): bool
    {
        $this->periodos->validar($desde, $hasta);

        $consulta = ReservaPersonaPeriodo::query()
            ->where('persona_id', $personaId)
            ->whereNull('liberado_at')
            ->whereDate('fecha_desde', '<=', $hasta)
            ->whereDate('fecha_hasta', '>=', $desde)
            ->when($excluirReservaId !== null, fn ($query) => $query->whereKeyNot($excluirReservaId));

        if ($bloquear) {
            $consulta->lockForUpdate();
        }

        return $consulta->first(['id']) !== null;
    }

    private function exigirTransaccion(): void
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('La validación con bloqueo requiere una transacción activa.');
        }
    }
}
