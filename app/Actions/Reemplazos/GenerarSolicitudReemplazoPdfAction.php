<?php

namespace App\Actions\Reemplazos;

use App\Actions\Tramites\TransicionarTramite;
use App\Models\DocumentoGenerado;
use App\Models\DocumentoPlantilla;
use App\Models\TipoDocumento;
use App\Models\Tramite;
use App\Models\User;
use App\Services\Documentos\DestinatarioSolicitudReemplazo;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class GenerarSolicitudReemplazoPdfAction
{
    public function __construct(private readonly TransicionarTramite $transicionar, private readonly DestinatarioSolicitudReemplazo $destinatario) {}

    public function execute(Tramite $tramite, User $user): DocumentoGenerado
    {
        if (! $user->can('reemplazos.revisar_personal') || ! $user->can('reemplazos.generar_documento') || Gate::forUser($user)->denies('view', $tramite)) {
            throw new AuthorizationException;
        }
        $storedPath = null;
        $tramite->historial()->create([
            'user_id' => $user->id,
            'action_code' => 'GENERACION_DOCUMENTO_INTENTADA',
            'occurred_at' => now(),
        ]);

        try {
            return DB::transaction(function () use ($tramite, $user, &$storedPath): DocumentoGenerado {
                $locked = Tramite::query()->lockForUpdate()->findOrFail($tramite->id);
                $locked->load(['tipoTramite', 'estadoTramite', 'unidadServicio', 'creador.roles', 'reemplazo.ausencia', 'reemplazo.tipoReemplazo', 'reemplazo.funcionario', 'reemplazo.funcionarioVinculo', 'reemplazo.reemplazante', 'reemplazo.estamento', 'reemplazo.profesion', 'revisionReemplazo.gradoEus', 'revisionReemplazo.clasificacionArea']);
                if ($locked->tipoTramite->codigo !== 'REEMPLAZO') {
                    throw ValidationException::withMessages(['tramite' => 'El trámite no corresponde a una Solicitud de Reemplazo.']);
                }
                $type = TipoDocumento::query()->where('codigo', 'DOCUMENTO_GENERADO')->firstOrFail();
                $existing = DocumentoGenerado::query()->with('adjunto')->where('tramite_id', $locked->id)->where('tipo_documento_id', $type->id)->where('status', 'VIGENTE')->lockForUpdate()->first();
                if ($existing && $this->isValid($existing)) {
                    return $existing->load(['adjunto', 'plantilla', 'generadoPor']);
                }
                if ($existing) {
                    $existing->update(['status' => 'ANULADO']);
                    $existing->adjunto?->update(['status' => 'ANULADO']);
                }
                if (! in_array($locked->estadoTramite->codigo, ['LISTA_GENERAR_DOCUMENTO', 'DOCUMENTO_GENERADO'], true)) {
                    throw ValidationException::withMessages(['tramite' => 'La solicitud no está lista para generar su documento.']);
                }
                if (! $locked->revisionReemplazo?->grado_eus_informado) {
                    throw ValidationException::withMessages(['grado_eus_informado' => 'Ingrese el último grado E.U.S. informado por Gestión de Personas.']);
                }
                $this->cleanOrphanFiles($locked, $type->id, $user);
                $template = DocumentoPlantilla::query()->where('codigo', 'REEMPLAZO_SOLICITUD_PDF')->where('active', true)->orderByDesc('version')->firstOrFail();
                $version = (int) DocumentoGenerado::query()->where('tramite_id', $locked->id)->where('tipo_documento_id', $type->id)->max('version') + 1;
                $bytes = Pdf::loadView($template->template_path, ['tramite' => $locked, 'destinatario' => $this->destinatario->para($locked->creador), 'generadoAt' => now()])->setPaper('a4')->output();
                if (! str_starts_with($bytes, '%PDF')) {
                    throw new \RuntimeException('El generador no produjo un PDF válido.');
                }
                $storedName = Str::ulid().'.pdf';
                $storedPath = 'tramites/'.$locked->public_id.'/'.$storedName;
                Storage::disk('private')->put($storedPath, $bytes);
                $adjunto = $locked->adjuntos()->create([
                    'tipo_documento_id' => $type->id, 'uploaded_by' => $user->id,
                    'original_name' => 'solicitud-reemplazo-'.$locked->codigo.'-v'.$version.'.pdf', 'stored_name' => $storedName,
                    'storage_path' => $storedPath, 'mime_type' => 'application/pdf', 'size_bytes' => strlen($bytes),
                    'sha256' => hash('sha256', $bytes), 'version' => $version, 'status' => 'ACTIVO',
                ]);
                $document = DocumentoGenerado::query()->create([
                    'tramite_id' => $locked->id, 'documento_plantilla_id' => $template->id, 'tipo_documento_id' => $type->id,
                    'adjunto_id' => $adjunto->id, 'version' => $version, 'generated_by' => $user->id, 'generated_at' => now(),
                    'status' => 'VIGENTE', 'metadata' => ['remitente' => $locked->reemplazo->remitente_snapshot, 'template_version' => $template->version],
                ]);
                if ($locked->estadoTramite->codigo === 'LISTA_GENERAR_DOCUMENTO') {
                    $this->transicionar->execute($locked, 'GENERAR_DOCUMENTO', $user, null, ['documento_generado_id' => $document->id]);
                }

                return $document->load(['adjunto', 'plantilla', 'generadoPor']);
            });
        } catch (Throwable $exception) {
            if ($storedPath) {
                Storage::disk('private')->delete($storedPath);
            }
            $tramite->historial()->create([
                'user_id' => $user->id,
                'action_code' => 'GENERACION_DOCUMENTO_FALLIDA',
                'metadata' => ['exception' => $exception::class],
                'occurred_at' => now(),
            ]);
            throw $exception;
        }
    }

    private function isValid(DocumentoGenerado $document): bool
    {
        if (! $document->adjunto || ! Storage::disk('private')->exists($document->adjunto->storage_path)) {
            return false;
        }

        $bytes = Storage::disk('private')->get($document->adjunto->storage_path);

        return str_starts_with($bytes, '%PDF') && hash_equals($document->adjunto->sha256, hash('sha256', $bytes));
    }

    private function cleanOrphanFiles(Tramite $tramite, int $typeId, User $user): void
    {
        $orphans = $tramite->adjuntos()->where('tipo_documento_id', $typeId)->where('status', 'ACTIVO')
            ->where('original_name', 'like', 'solicitud-reemplazo-%')->whereDoesntHave('documentoGenerado')->lockForUpdate()->get();

        foreach ($orphans as $orphan) {
            Storage::disk('private')->delete($orphan->storage_path);
            $orphan->update(['status' => 'ANULADO']);
            $tramite->historial()->create([
                'user_id' => $user->id,
                'action_code' => 'ARCHIVO_PARCIAL_LIMPIADO',
                'metadata' => ['adjunto_id' => $orphan->id],
                'occurred_at' => now(),
            ]);
        }
    }
}
