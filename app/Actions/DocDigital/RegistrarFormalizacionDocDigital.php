<?php

namespace App\Actions\DocDigital;

use App\Actions\Tramites\Adjuntos\CargarAdjunto;
use App\Actions\Tramites\TransicionarTramite;
use App\Models\DocDigitalRegistro;
use App\Models\TipoDocumento;
use App\Models\Tramite;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class RegistrarFormalizacionDocDigital
{
    public function __construct(private readonly CargarAdjunto $cargarAdjunto, private readonly TransicionarTramite $transicionar) {}

    public function execute(Tramite $tramite, UploadedFile $archivoFinal, CarbonInterface $fecha, User $user, ?string $identificador = null, ?string $observacion = null): DocDigitalRegistro
    {
        if (! $user->can('docdigital.registrar_formalizacion') || ! $user->can('tramites.adjuntos.cargar') || Gate::forUser($user)->denies('view', $tramite)) {
            throw new AuthorizationException('No está autorizado para registrar la formalización.');
        }
        $storedPath = null;
        try {
            return DB::transaction(function () use ($tramite, $archivoFinal, $fecha, $user, $identificador, $observacion, &$storedPath): DocDigitalRegistro {
                $locked = Tramite::query()->with('estadoTramite')->lockForUpdate()->findOrFail($tramite->id);
                if ($locked->estadoTramite->codigo !== 'ENVIADA_DOCDIGITAL') {
                    throw ValidationException::withMessages(['tramite' => 'El trámite debe estar enviado a DocDigital.']);
                }
                $record = DocDigitalRegistro::query()->where('tramite_id', $locked->id)->where('is_current', true)->lockForUpdate()->first();
                if (! $record || $record->estado !== 'ENVIADO') {
                    throw ValidationException::withMessages(['tramite' => 'No existe un envío actual pendiente de formalización.']);
                }
                $type = TipoDocumento::query()->where('codigo', 'DOCUMENTO_FINAL_DOCDIGITAL')->where('active', true)->firstOrFail();
                $final = $this->cargarAdjunto->execute($locked, $archivoFinal, $user, $type->id);
                $storedPath = $final->storage_path;
                $record->update([
                    'estado' => 'FORMALIZADO', 'formalizado_por' => $user->id, 'fecha_formalizacion' => $fecha,
                    'adjunto_final_id' => $final->id,
                    'identificador_externo' => filled($identificador) ? trim($identificador) : $record->identificador_externo,
                    'observacion_formalizacion' => filled($observacion) ? trim($observacion) : null,
                ]);
                $locked->historial()->create([
                    'user_id' => $user->id, 'action_code' => 'DOCDIGITAL_FORMALIZACION_REGISTRADA',
                    'metadata' => ['registro_docdigital_id' => $record->id, 'intento' => $record->intento, 'adjunto_final_id' => $final->id], 'occurred_at' => now(),
                ]);
                $this->transicionar->execute($locked, 'REGISTRAR_FORMALIZACION', $user, metadata: ['registro_docdigital_id' => $record->id, 'adjunto_final_id' => $final->id]);

                return $record->refresh();
            });
        } catch (Throwable $exception) {
            if ($storedPath) {
                Storage::disk('private')->delete($storedPath);
            }
            throw $exception;
        }
    }
}
