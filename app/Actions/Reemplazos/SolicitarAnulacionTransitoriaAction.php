<?php

namespace App\Actions\Reemplazos;

use App\Enums\ModalidadSolicitudContrato;
use App\Models\Tramite;
use App\Models\TramiteHistorial;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class SolicitarAnulacionTransitoriaAction
{
    /** Registra una petición interna; su ejecución requiere un caso de uso separado. */
    public function execute(Tramite $tramite, User $actor, string $motivo): TramiteHistorial
    {
        if (blank($motivo) || mb_strlen(trim($motivo)) > 2000) {
            throw ValidationException::withMessages(['motivo' => 'Indique un motivo de hasta 2000 caracteres.']);
        }

        return DB::transaction(function () use ($tramite, $actor, $motivo): TramiteHistorial {
            $tramite = Tramite::query()->lockForUpdate()->findOrFail($tramite->id);
            if ($tramite->tipoTramite()->value('codigo') !== 'REEMPLAZO'
                || $tramite->solicitudContrato?->modalidad !== ModalidadSolicitudContrato::TRANSITORIA) {
                throw ValidationException::withMessages(['solicitud' => 'La petición requiere una solicitud transitoria V3.']);
            }

            if (! in_array($tramite->estadoTramite?->codigo, ['BORRADOR', 'ENVIADA_GESTION_PERSONAS', 'EN_REVISION', 'DEVUELTA_PARA_CORRECCION', 'LISTA_GENERAR_DOCUMENTO'], true)) {
                throw ValidationException::withMessages(['solicitud' => 'La petición administrativa no está habilitada en este estado.']);
            }
            $primerEnvio = $tramite->historial()
                ->whereIn('action_code', ['ENVIAR_A_GESTION_PERSONAS', 'REENVIAR_A_GESTION_PERSONAS'])
                ->first();
            if ($primerEnvio === null) {
                throw ValidationException::withMessages(['solicitud' => 'Solo puede solicitar anulación después del primer envío registrado.']);
            }

            Gate::forUser($actor)->authorize('solicitar-anulacion-reemplazo', $tramite);

            return $tramite->historial()->create([
                'user_id' => $actor->id,
                'action_code' => 'SOLICITAR_ANULACION',
                'from_estado_id' => $tramite->estado_tramite_id,
                'to_estado_id' => $tramite->estado_tramite_id,
                'observation' => trim($motivo),
                'metadata' => ['primer_envio_historial_id' => $primerEnvio->id, 'resultado' => 'PENDIENTE'],
                'occurred_at' => now(),
            ]);
        });
    }
}
