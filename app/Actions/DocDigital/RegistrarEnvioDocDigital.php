<?php

namespace App\Actions\DocDigital;

use App\Actions\Tramites\TransicionarTramite;
use App\Models\DocDigitalRegistro;
use App\Models\DocumentoGenerado;
use App\Models\Tramite;
use App\Models\TramiteAdjunto;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class RegistrarEnvioDocDigital
{
    public function __construct(private readonly TransicionarTramite $transicionar) {}

    public function execute(Tramite $tramite, TramiteAdjunto $adjunto, CarbonInterface $fechaEnvio, User $user, ?string $identificador = null, ?string $observacion = null): DocDigitalRegistro
    {
        if (! $user->can('docdigital.registrar_envio') || Gate::forUser($user)->denies('view', $tramite)) {
            throw new AuthorizationException('No está autorizado para registrar el envío a DocDigital.');
        }
        if ($adjunto->tramite_id !== $tramite->id || $adjunto->status !== 'ACTIVO' || ! Storage::disk('private')->exists($adjunto->storage_path)) {
            throw ValidationException::withMessages(['adjunto_enviado_id' => 'El documento debe ser un archivo vigente y existente del mismo trámite.']);
        }

        return DB::transaction(function () use ($tramite, $adjunto, $fechaEnvio, $user, $identificador, $observacion): DocDigitalRegistro {
            $locked = Tramite::query()->with('estadoTramite')->lockForUpdate()->findOrFail($tramite->id);
            $allowed = $locked->tipoTramite()->value('codigo') === 'HORAS_EXTRAORDINARIAS' ? ['INFORME_TECNICO_GENERADO', 'ENVIADA_DOCDIGITAL'] : ['DOCUMENTO_GENERADO', 'ENVIADA_DOCDIGITAL'];
            if (! in_array($locked->estadoTramite->codigo, $allowed, true)) {
                $message = $locked->tipoTramite()->value('codigo') === 'REEMPLAZO' && $locked->estadoTramite->codigo === 'LISTA_GENERAR_DOCUMENTO'
                    ? 'Plantilla institucional pendiente de validación por RRHH.'
                    : 'El trámite no está preparado para enviarse a DocDigital.';
                throw ValidationException::withMessages(['tramite' => $message]);
            }

            $current = DocDigitalRegistro::query()->where('tramite_id', $locked->id)->where('is_current', true)->lockForUpdate()->first();
            if ($current?->estado === 'FORMALIZADO') {
                throw ValidationException::withMessages(['tramite' => 'Un trámite formalizado no admite reenvíos.']);
            }
            $attempt = (int) DocDigitalRegistro::query()->where('tramite_id', $locked->id)->max('intento') + 1;
            $current?->update(['is_current' => false]);
            $generated = DocumentoGenerado::query()->where('tramite_id', $locked->id)->where('adjunto_id', $adjunto->id)->first();
            $record = DocDigitalRegistro::query()->create([
                'tramite_id' => $locked->id, 'documento_generado_id' => $generated?->id,
                'adjunto_enviado_id' => $adjunto->id, 'registrado_por' => $user->id,
                'fecha_envio' => $fechaEnvio, 'identificador_externo' => filled($identificador) ? trim($identificador) : null,
                'observacion_envio' => filled($observacion) ? trim($observacion) : null,
                'estado' => 'ENVIADO', 'intento' => $attempt, 'is_current' => true,
            ]);
            $locked->historial()->create([
                'user_id' => $user->id, 'action_code' => $attempt === 1 ? 'DOCDIGITAL_ENVIO_REGISTRADO' : 'DOCDIGITAL_REENVIO_REGISTRADO',
                'metadata' => array_filter(['registro_docdigital_id' => $record->id, 'intento' => $attempt, 'documento_generado_id' => $generated?->id, 'adjunto_enviado_id' => $adjunto->id, 'identificador_externo' => $record->identificador_externo], fn ($value) => $value !== null),
                'occurred_at' => now(),
            ]);
            if ($locked->estadoTramite->codigo !== 'ENVIADA_DOCDIGITAL') {
                $this->transicionar->execute($locked, 'REGISTRAR_ENVIO_DOCDIGITAL', $user, metadata: ['registro_docdigital_id' => $record->id, 'intento' => $attempt]);
            }

            return $record->refresh();
        });
    }
}
