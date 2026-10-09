<?php

namespace App\Actions\Reemplazos;

use App\Enums\ModalidadSolicitudContrato;
use App\Models\RespaldoTransitorio;
use App\Models\SolicitudContrato;
use App\Models\Tramite;
use App\Models\TramiteReemplazo;
use App\Models\User;
use App\Services\Respaldos\EscriturasPeriodosTransitorios;
use App\Services\Respaldos\PeriodosInclusivos;
use App\Services\Respaldos\TransaccionPeriodosTransitorios;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class RegistrarRespaldoSolicitudTransitoriaAction
{
    public function __construct(
        private readonly TransaccionPeriodosTransitorios $transaccion,
        private readonly PeriodosInclusivos $periodos,
        private readonly EscriturasPeriodosTransitorios $escrituras,
    ) {}

    public function execute(Tramite $tramite, User $actor, array $datos): RespaldoTransitorio
    {
        $detallePrevisto = TramiteReemplazo::query()->where('tramite_id', $tramite->id)->first();
        if ($detallePrevisto?->funcionario_id === null) {
            throw ValidationException::withMessages(['funcionario_id' => 'Guarde primero el funcionario y su período en el borrador.']);
        }

        return $this->transaccion->ejecutar([$detallePrevisto->funcionario_id], function () use ($tramite, $actor, $datos, $detallePrevisto): RespaldoTransitorio {
            $tramite = Tramite::query()->lockForUpdate()->findOrFail($tramite->id);
            $solicitud = SolicitudContrato::query()->where('tramite_id', $tramite->id)->lockForUpdate()->first();
            $detalle = TramiteReemplazo::query()->where('tramite_id', $tramite->id)->lockForUpdate()->first();
            if ($solicitud?->modalidad !== ModalidadSolicitudContrato::TRANSITORIA || $detalle === null || $tramite->tipoTramite()->value('codigo') !== 'REEMPLAZO') {
                throw ValidationException::withMessages(['solicitud' => 'Solo se admiten respaldos de solicitudes transitorias V3.']);
            }
            Gate::forUser($actor)->authorize('editar-reemplazo', $tramite);
            if ($tramite->estadoTramite()->value('codigo') !== 'BORRADOR') {
                throw ValidationException::withMessages(['solicitud' => 'El respaldo debe registrarse antes del envío.']);
            }
            if ($detalle->funcionario_id !== $detallePrevisto->funcionario_id || $detalle->fecha_funcionario_desde === null || $detalle->fecha_funcionario_hasta === null) {
                throw ValidationException::withMessages(['funcionario_id' => 'El funcionario o su período cambiaron; guarde y reintente.']);
            }
            $desde = $detalle->fecha_funcionario_desde->toDateString();
            $hasta = $detalle->fecha_funcionario_hasta->toDateString();
            $this->periodos->validar($datos['fecha_desde'], $datos['fecha_hasta']);
            if (! $this->periodos->contiene($datos['fecha_desde'], $datos['fecha_hasta'], $desde, $hasta)) {
                throw ValidationException::withMessages(['fecha_desde' => 'El respaldo debe contener el período completo del funcionario.']);
            }

            $respaldo = $this->escrituras->registrarRespaldoBajoBloqueo($solicitud, $actor, [
                'funcionario_origen_id' => $detalle->funcionario_id,
                'unidad_origen_id' => $solicitud->unidad_origen_id,
                'motivo' => $datos['motivo'],
                'fecha_desde' => $datos['fecha_desde'],
                'fecha_hasta' => $datos['fecha_hasta'],
                'referencia_externa' => $datos['referencia_externa'] ?? null,
            ]);
            $tramite->historial()->create([
                'user_id' => $actor->id,
                'action_code' => 'RESPALDO_TRANSITORIO_REGISTRADO',
                'from_estado_id' => $tramite->estado_tramite_id,
                'to_estado_id' => $tramite->estado_tramite_id,
                'metadata' => ['respaldo_id' => $respaldo->id, 'respaldo_version_id' => $respaldo->versionActual->id],
                'occurred_at' => now(),
            ]);

            return $respaldo;
        });
    }
}
