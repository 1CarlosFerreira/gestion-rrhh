<?php

namespace App\Actions\Reemplazos;

use App\Actions\Tramites\TransicionarTramite;
use App\Enums\ModalidadSolicitudContrato;
use App\Models\ReservaPersonaPeriodo;
use App\Models\RespaldoAfectacion;
use App\Models\RespaldoTransitorio;
use App\Models\SolicitudContrato;
use App\Models\Tramite;
use App\Models\TramiteReemplazo;
use App\Models\User;
use App\Services\Reemplazos\AnulacionTransitoria;
use App\Services\Respaldos\TransaccionPeriodosTransitorios;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class AnularSolicitudTransitoriaAction
{
    private ?array $ejecucion = null;

    public function __construct(private readonly TransaccionPeriodosTransitorios $transaccion, private readonly TransicionarTramite $transicionar) {}

    public function execute(Tramite $tramite, User $actor, string $motivo): Tramite
    {
        if (blank($motivo) || mb_strlen(trim($motivo)) > 2000) {
            throw ValidationException::withMessages(['motivo' => 'Indique un motivo de hasta 2000 caracteres.']);
        }
        // Lecturas preliminares solo para determinar qué personas bloquear.
        $detallePrevisto = TramiteReemplazo::query()->where('tramite_id', $tramite->id)->first();
        $solicitudId = SolicitudContrato::query()->where('tramite_id', $tramite->id)->value('id');
        $previstas = RespaldoAfectacion::query()->with(['versionRespaldo', 'reservas'])->where('solicitud_contrato_id', $solicitudId)->whereNull('liberado_at')->get();
        $personas = collect([$detallePrevisto?->funcionario_id, $detallePrevisto?->reemplazante_id]);
        foreach ($previstas as $afectacion) {
            $personas->push($afectacion->versionRespaldo->funcionario_origen_id);
            foreach ($afectacion->reservas as $reserva) {
                if ($reserva->liberado_at === null) {
                    $personas->push($reserva->persona_id);
                }
            }
        }
        $personas = $personas->filter()->unique()->sort()->values()->all();

        return $this->transaccion->ejecutar($personas, function () use ($tramite, $actor, $motivo, $detallePrevisto, $personas): Tramite {
            $tramite = Tramite::query()->lockForUpdate()->findOrFail($tramite->id);
            $solicitud = SolicitudContrato::query()->where('tramite_id', $tramite->id)->lockForUpdate()->first();
            $detalle = TramiteReemplazo::query()->where('tramite_id', $tramite->id)->lockForUpdate()->first();
            if ($solicitud?->modalidad !== ModalidadSolicitudContrato::TRANSITORIA || $detalle === null || $tramite->tipoTramite()->value('codigo') !== 'REEMPLAZO') {
                throw ValidationException::withMessages(['solicitud' => 'La anulación requiere una solicitud transitoria V3.']);
            }
            if ($detalle->funcionario_id !== $detallePrevisto?->funcionario_id || $detalle->reemplazante_id !== $detallePrevisto?->reemplazante_id) {
                throw ValidationException::withMessages(['solicitud' => 'Las personas cambiaron; reintente la anulación.']);
            }
            if (! app(AnulacionTransitoria::class)->antesDelDocumento($tramite)) {
                throw ValidationException::withMessages(['solicitud' => 'La solicitud está anulada o alcanzó una etapa incompatible con esta operación.']);
            }
            Gate::forUser($actor)->authorize('anular-reemplazo', $tramite);
            $afectaciones = RespaldoAfectacion::query()->where('solicitud_contrato_id', $solicitud->id)->whereNull('liberado_at')->orderBy('respaldo_id')->get();
            $metadata = ['afectaciones_liberadas' => [], 'reservas_liberadas' => [], 'peticiones_historial_ids' => $tramite->historial()->where('action_code', 'SOLICITAR_ANULACION')->pluck('id')->all()];
            $liberacion = ['liberado_at' => now(), 'liberado_por' => $actor->id, 'motivo_liberacion' => trim($motivo)];
            foreach ($afectaciones as $previa) {
                RespaldoTransitorio::query()->lockForUpdate()->findOrFail($previa->respaldo_id);
                $afectacion = RespaldoAfectacion::query()->lockForUpdate()->findOrFail($previa->id);
                if (! in_array($afectacion->versionRespaldo->funcionario_origen_id, $personas, true)) {
                    throw ValidationException::withMessages(['solicitud' => 'El compromiso cambió; reintente la anulación.']);
                }
                $reservas = ReservaPersonaPeriodo::query()->where('afectacion_id', $afectacion->id)->whereNull('liberado_at')->orderBy('id')->lockForUpdate()->get();
                foreach ($reservas as $reserva) {
                    if (! in_array($reserva->persona_id, $personas, true)) {
                        throw ValidationException::withMessages(['solicitud' => 'La reserva cambió; reintente la anulación.']);
                    }
                    $reserva->update($liberacion);
                    $metadata['reservas_liberadas'][] = $reserva->id;
                }
                $afectacion->update($liberacion);
                $metadata['afectaciones_liberadas'][] = $afectacion->id;
            }
            $metadata['liberado_at'] = $liberacion['liberado_at']->toIso8601String();
            $metadata['primer_envio_historial_id'] = $tramite->historial()->whereIn('action_code', ['ENVIAR_A_GESTION_PERSONAS', 'REENVIAR_A_GESTION_PERSONAS'])->value('id');
            $this->ejecucion = [$tramite->id, $actor->id];
            try {
                return $this->transicionar->execute($tramite, 'ANULAR_SOLICITUD', $actor, trim($motivo), $metadata);
            } finally {
                $this->ejecucion = null;
            }
        });
    }

    public function validarTransicion(Tramite $tramite, User $actor): void
    {
        if ($this->ejecucion !== [$tramite->id, $actor->id]
            || $tramite->solicitudContrato->afectacionesRespaldo()->whereNull('liberado_at')->exists()
            || $tramite->solicitudContrato->reservasPersona()->whereNull('reserva_persona_periodos.liberado_at')->exists()) {
            throw ValidationException::withMessages(['solicitud' => 'La anulación debe ejecutar todas sus liberaciones mediante la acción transaccional.']);
        }
    }
}
