<?php

namespace App\Actions\Tramites\Adjuntos;

use App\Models\Tramite;
use App\Models\TramiteAdjunto;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class CargarAdjunto
{
    private const EXTENSIONS = [
        'application/pdf' => 'pdf', 'application/msword' => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'application/vnd.ms-excel' => 'xls',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
        'text/csv' => 'csv', 'text/plain' => 'csv', 'image/jpeg' => 'jpg', 'image/png' => 'png',
    ];

    public function execute(Tramite $tramite, UploadedFile $file, User $user, ?int $tipoDocumentoId = null, ?int $personaId = null, ?TramiteAdjunto $replaces = null, string $permission = 'tramites.adjuntos.cargar'): TramiteAdjunto
    {
        $this->authorize($tramite, $user, $permission);
        if ($replaces && $replaces->tramite_id !== $tramite->id) {
            throw new AuthorizationException('El adjunto a versionar no pertenece al trámite.');
        }

        $mime = $file->getMimeType();
        if (! isset(self::EXTENSIONS[$mime])) {
            throw new \InvalidArgumentException('El MIME real del archivo no está permitido.');
        }
        $storedName = Str::ulid().'.'.self::EXTENSIONS[$mime];
        $path = 'tramites/'.$tramite->public_id.'/'.$storedName;
        Storage::disk('private')->putFileAs('tramites/'.$tramite->public_id, $file, $storedName);

        try {
            return DB::transaction(function () use ($tramite, $file, $user, $tipoDocumentoId, $personaId, $replaces, $mime, $storedName, $path): TramiteAdjunto {
                $previous = $replaces ? TramiteAdjunto::query()->lockForUpdate()->findOrFail($replaces->id) : null;
                if ($previous) {
                    $previous->update(['status' => 'REEMPLAZADO']);
                }
                $adjunto = $tramite->adjuntos()->create([
                    'tipo_documento_id' => $tipoDocumentoId ?? $previous?->tipo_documento_id,
                    'persona_id' => $personaId ?? $previous?->persona_id,
                    'uploaded_by' => $user->id,
                    'original_name' => Str::limit(preg_replace('/[\x00-\x1F\x7F]/u', '', basename($file->getClientOriginalName())) ?: 'archivo', 255, ''),
                    'stored_name' => $storedName,
                    'storage_path' => $path,
                    'mime_type' => $mime,
                    'size_bytes' => Storage::disk('private')->size($path),
                    'sha256' => hash_file('sha256', Storage::disk('private')->path($path)),
                    'version' => $previous ? $previous->version + 1 : 1,
                    'replaces_adjunto_id' => $previous?->id,
                    'status' => 'ACTIVO',
                ]);
                $tramite->historial()->create([
                    'user_id' => $user->id,
                    'action_code' => $previous ? 'ADJUNTO_VERSIONADO' : 'ADJUNTO_CARGADO',
                    'metadata' => ['adjunto_id' => $adjunto->id, 'tipo_documento_id' => $adjunto->tipo_documento_id, 'version' => $adjunto->version, 'mime' => $mime, 'size_bytes' => $adjunto->size_bytes],
                    'occurred_at' => now(),
                ]);

                return $adjunto;
            });
        } catch (Throwable $exception) {
            Storage::disk('private')->delete($path);
            throw $exception;
        }
    }

    private function authorize(Tramite $tramite, User $user, string $permission): void
    {
        if (! $user->can($permission) || Gate::forUser($user)->denies('view', $tramite)) {
            throw new AuthorizationException('No está autorizado para cargar adjuntos.');
        }
    }
}
