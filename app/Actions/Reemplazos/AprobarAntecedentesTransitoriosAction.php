<?php

namespace App\Actions\Reemplazos;

use App\Actions\Tramites\TransicionarTramite;
use App\Enums\ModalidadSolicitudContrato;
use App\Models\RespaldoAfectacion;
use App\Models\RespaldoTransitorio;
use App\Models\RespaldoTransitorioVersion;
use App\Models\SolicitudContrato;
use App\Models\Tramite;
use App\Models\TramiteReemplazo;
use App\Models\User;
use App\Services\Respaldos\ConflictosPeriodosTransitorios;
use App\Services\Respaldos\EscriturasPeriodosTransitorios;
use App\Services\Respaldos\PeriodosInclusivos;
use App\Services\Respaldos\TransaccionPeriodosTransitorios;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class AprobarAntecedentesTransitoriosAction
{
    public function __construct(
        private readonly TransaccionPeriodosTransitorios $transaccion,
        private readonly PeriodosInclusivos $periodos,
        private readonly ConflictosPeriodosTransitorios $conflictos,
        private readonly EscriturasPeriodosTransitorios $reservas,
        private readonly GuardarRevisionReemplazoAction $guardarRevision,
        private readonly TransicionarTramite $transicionar,
    ) {}

    /** Caso de uso atómico V3 invocado por la ruta pública de aprobación. */
    public function execute(Tramite $tramite, RespaldoTransitorio $respaldo, int $versionEsperadaId, array $revision, User $actor): Tramite
    {
        // Estas lecturas solo determinan qué personas bloquear; todo se vuelve a comprobar bajo lock.
        $detallePrevisto = TramiteReemplazo::query()->where('tramite_id', $tramite->id)->first();
        $versionPrevista = RespaldoTransitorioVersion::query()->whereKey($versionEsperadaId)->where('respaldo_id', $respaldo->id)->first();
        if ($detallePrevisto?->funcionario_id === null) {
            throw ValidationException::withMessages(['solicitud' => 'La solicitud no tiene funcionario origen.']);
        }
        if ($versionPrevista === null) {
            throw ValidationException::withMessages(['respaldo_version_id' => 'La versión de respaldo indicada no está disponible.']);
        }

        $personas = array_filter([$detallePrevisto->funcionario_id, $detallePrevisto->reemplazante_id, $versionPrevista->funcionario_origen_id]);

        try {
            return $this->transaccion->ejecutar($personas, function () use ($tramite, $respaldo, $versionEsperadaId, $detallePrevisto, $revision, $actor): Tramite {
                $tramite = Tramite::query()->lockForUpdate()->findOrFail($tramite->id);
                $solicitud = SolicitudContrato::query()->where('tramite_id', $tramite->id)->lockForUpdate()->first();
                $detalle = TramiteReemplazo::query()->where('tramite_id', $tramite->id)->lockForUpdate()->first();
                if ($solicitud?->modalidad !== ModalidadSolicitudContrato::TRANSITORIA || $detalle === null || $tramite->tipoTramite()->value('codigo') !== 'REEMPLAZO') {
                    throw ValidationException::withMessages(['solicitud' => 'Solo se pueden aprobar solicitudes transitorias V3 de reemplazo.']);
                }
                if ($detalle->funcionario_id !== $detallePrevisto->funcionario_id || $detalle->reemplazante_id !== $detallePrevisto->reemplazante_id) {
                    throw ValidationException::withMessages(['solicitud' => 'Las personas de la solicitud cambiaron; reintente la aprobación.']);
                }
                if ($tramite->estadoTramite()->value('codigo') !== 'EN_REVISION') {
                    throw ValidationException::withMessages(['solicitud' => 'La solicitud no se encuentra en revisión.']);
                }

                Gate::forUser($actor)->authorize('revisar-reemplazo', $tramite);
                $this->validarSolicitud($tramite, $solicitud, $detalle);

                RespaldoTransitorio::query()->lockForUpdate()->findOrFail($respaldo->id);
                $version = RespaldoTransitorioVersion::query()->where('respaldo_id', $respaldo->id)->orderByDesc('version')->lockForUpdate()->first();
                if ($version?->id !== $versionEsperadaId) {
                    throw ValidationException::withMessages(['respaldo_version_id' => 'La versión del respaldo cambió; reintente la aprobación.']);
                }
                if ($version->funcionario_origen_id !== $detalle->funcionario_id || $version->unidad_origen_id !== $solicitud->unidad_origen_id) {
                    throw ValidationException::withMessages(['respaldo' => 'El respaldo no corresponde al funcionario y unidad origen de la solicitud.']);
                }

                $desdeRespaldo = $version->fecha_desde->toDateString();
                $hastaRespaldo = $version->fecha_hasta->toDateString();
                $desdeOrigen = $detalle->fecha_funcionario_desde->toDateString();
                $hastaOrigen = $detalle->fecha_funcionario_hasta->toDateString();
                if (! $this->periodos->contiene($desdeRespaldo, $hastaRespaldo, $desdeOrigen, $hastaOrigen)) {
                    throw ValidationException::withMessages(['fecha_funcionario_desde' => 'El período del funcionario debe quedar dentro del respaldo.']);
                }
                if ($this->conflictos->respaldoBajoBloqueo($detalle->funcionario_id, $desdeRespaldo, $hastaRespaldo, $respaldo->id)) {
                    throw ValidationException::withMessages(['respaldo' => 'El funcionario posee otro respaldo con un período superpuesto.']);
                }

                if (RespaldoAfectacion::query()->where('respaldo_id', $respaldo->id)->whereNull('liberado_at')->exists()
                    || RespaldoAfectacion::query()->where('solicitud_contrato_id', $solicitud->id)->whereNull('liberado_at')->exists()) {
                    throw ValidationException::withMessages(['respaldo' => 'El respaldo o la solicitud ya tienen un compromiso vigente.']);
                }

                $afectacion = RespaldoAfectacion::query()->create([
                    'respaldo_id' => $respaldo->id,
                    'respaldo_version_id' => $version->id,
                    'solicitud_contrato_id' => $solicitud->id,
                    'comprometido_at' => now(),
                    'comprometido_por' => $actor->id,
                ]);

                $metadata = ['respaldo_id' => $respaldo->id, 'respaldo_version_id' => $version->id, 'afectacion_id' => $afectacion->id];
                if ($detalle->reemplazante_id !== null) {
                    $desdeReserva = $detalle->fecha_reemplazante_desde->toDateString();
                    $hastaReserva = $detalle->fecha_reemplazante_hasta->toDateString();
                    $reserva = $this->reservas->registrarReservaBajoBloqueo($afectacion, $actor, $detalle->reemplazante_id, $desdeReserva, $hastaReserva);
                    $metadata += ['reserva_id' => $reserva->id, 'persona_reservada_id' => $reserva->persona_id, 'periodo_reserva_desde' => $desdeReserva, 'periodo_reserva_hasta' => $hastaReserva];
                }

                $revisionGuardada = $this->guardarRevision->execute($tramite, $revision, $actor);
                $revisionGuardada->update(['revisado_por' => $actor->id, 'revisado_at' => now()]);

                return $this->transicionar->execute($tramite, 'APROBAR_ANTECEDENTES', $actor, metadata: ['revision_id' => $revisionGuardada->id, ...$metadata]);
            });
        } catch (QueryException $exception) {
            if ((string) $exception->getCode() === '23000' && (int) ($exception->errorInfo[1] ?? 0) === 1062) {
                throw ValidationException::withMessages(['respaldo' => 'La solicitud o el respaldo fueron comprometidos en otra operación.']);
            }

            throw $exception;
        }
    }

    private function validarSolicitud(Tramite $tramite, SolicitudContrato $solicitud, TramiteReemplazo $detalle): void
    {
        if ($solicitud->unidad_solicitante_id !== $tramite->unidad_organizacional_id
            || $solicitud->unidad_origen_id === null || $solicitud->unidad_destino_id === null
            || $detalle->funcionario_id === null || $detalle->tipo_reemplazo_id === null
            || blank($detalle->justificacion) || $detalle->fecha_funcionario_desde === null || $detalle->fecha_funcionario_hasta === null) {
            throw ValidationException::withMessages(['solicitud' => 'La solicitud transitoria no tiene antecedentes completos para aprobar.']);
        }
        $this->periodos->validar($detalle->fecha_funcionario_desde->toDateString(), $detalle->fecha_funcionario_hasta->toDateString());
        if ($detalle->reemplazante_id !== null) {
            if ($detalle->fecha_reemplazante_desde === null || $detalle->fecha_reemplazante_hasta === null) {
                throw ValidationException::withMessages(['fecha_reemplazante_desde' => 'La persona propuesta requiere un período de reserva.']);
            }
            $desde = $detalle->fecha_reemplazante_desde->toDateString();
            $hasta = $detalle->fecha_reemplazante_hasta->toDateString();
            $this->periodos->validar($desde, $hasta);
            if (! $this->periodos->contiene($detalle->fecha_funcionario_desde->toDateString(), $detalle->fecha_funcionario_hasta->toDateString(), $desde, $hasta)) {
                throw ValidationException::withMessages(['fecha_reemplazante_desde' => 'La cobertura propuesta debe quedar dentro del período del funcionario.']);
            }
        }
    }
}
