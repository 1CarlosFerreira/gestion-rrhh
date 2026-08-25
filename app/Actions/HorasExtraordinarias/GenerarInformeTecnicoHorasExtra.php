<?php

namespace App\Actions\HorasExtraordinarias;

use App\Actions\Tramites\Adjuntos\CargarAdjunto;
use App\Actions\Tramites\TransicionarTramite;
use App\Models\DocumentoGenerado;
use App\Models\DocumentoPlantilla;
use App\Models\TipoDocumento;
use App\Models\Tramite;
use App\Models\TramiteAdjunto;
use App\Models\User;
use App\Services\Documentos\GeneradorInformeTecnicoXlsx;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class GenerarInformeTecnicoHorasExtra
{
    public function __construct(
        private readonly GeneradorInformeTecnicoXlsx $xlsx,
        private readonly CargarAdjunto $cargarAdjunto,
        private readonly TransicionarTramite $transicionar,
    ) {}

    public function execute(Tramite $tramite, User $user): DocumentoGenerado
    {
        if (! $user->can('documentos.generar') || Gate::forUser($user)->denies('view', $tramite)) {
            throw new AuthorizationException;
        }
        $tramite->refresh()->load(['tipoTramite', 'estadoTramite', 'unidadServicio', 'horasExtra.informeTecnico', 'horasExtra.funcionarios.persona', 'horasExtra.funcionarios.planillaVigente']);
        if ($tramite->tipoTramite->codigo !== 'HORAS_EXTRAORDINARIAS' || ! in_array($tramite->estadoTramite->codigo, ['CONFORME', 'INFORME_TECNICO_GENERADO'], true)) {
            throw ValidationException::withMessages(['tramite' => 'El Informe Técnico solo puede generarse desde un trámite HE conforme.']);
        }
        $this->validateDomain($tramite);
        $template = $this->resolveTemplate();
        $templatePath = base_path($template->template_path);
        if (! is_file($templatePath) || ($template->sha256 && ! hash_equals($template->sha256, hash_file('sha256', $templatePath)))) {
            throw new RuntimeException('La plantilla institucional falta o no coincide con su hash registrado.');
        }

        $temporaryPath = $this->xlsx->generate($templatePath, $tramite, $tramite->horasExtra->informeTecnico);
        $storedAdjunto = null;
        try {
            return DB::transaction(function () use ($tramite, $user, $template, $temporaryPath, &$storedAdjunto): DocumentoGenerado {
                $type = TipoDocumento::query()->where('codigo', 'INFORME_TECNICO')->firstOrFail();
                $previous = DocumentoGenerado::query()->where('tramite_id', $tramite->id)->where('tipo_documento_id', $type->id)->where('status', 'VIGENTE')->lockForUpdate()->first();
                $version = ($previous?->version ?? 0) + 1;
                $upload = new UploadedFile($temporaryPath, 'informe_tecnico_'.$tramite->codigo.'_v'.$version.'.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
                $previousAdjunto = $previous ? TramiteAdjunto::query()->findOrFail($previous->adjunto_id) : null;
                $storedAdjunto = $this->cargarAdjunto->execute($tramite, $upload, $user, $type->id, null, $previousAdjunto);
                if ($previous) {
                    $previous->update(['status' => 'REEMPLAZADO']);
                }
                $metadata = [
                    'participant_ids' => $tramite->horasExtra->funcionarios->pluck('id')->all(),
                    'minutes' => $tramite->horasExtra->funcionarios->map(fn ($participant) => ['id' => $participant->id, 'daytime' => $participant->daytime_minutes, 'night_festive' => $participant->night_festive_minutes, 'total' => $participant->total_minutes])->all(),
                    'period' => [$tramite->horasExtra->period_start->toDateString(), $tramite->horasExtra->period_end->toDateString()],
                    'template' => ['id' => $template->id, 'version' => $template->version],
                ];
                $document = DocumentoGenerado::query()->create([
                    'tramite_id' => $tramite->id, 'documento_plantilla_id' => $template->id,
                    'tipo_documento_id' => $type->id, 'adjunto_id' => $storedAdjunto->id,
                    'version' => $version, 'generated_by' => $user->id, 'generated_at' => now(),
                    'status' => 'VIGENTE', 'metadata' => $metadata,
                ]);
                $tramite->historial()->create([
                    'user_id' => $user->id, 'action_code' => $previous ? 'DOCUMENTO_REGENERADO' : 'DOCUMENTO_GENERADO',
                    'metadata' => ['documento_generado_id' => $document->id, 'adjunto_id' => $storedAdjunto->id, 'version' => $version, 'plantilla_id' => $template->id], 'occurred_at' => now(),
                ]);
                if ($tramite->estadoTramite->codigo === 'CONFORME') {
                    $this->transicionar->execute($tramite, 'GENERAR_INFORME_TECNICO', $user);
                }

                return $document->load(['adjunto', 'plantilla', 'generadoPor']);
            });
        } catch (Throwable $exception) {
            if ($storedAdjunto) {
                Storage::disk('private')->delete($storedAdjunto->storage_path);
            }
            throw $exception;
        } finally {
            @unlink($temporaryPath);
        }
    }

    private function validateDomain(Tramite $tramite): void
    {
        $report = $tramite->horasExtra->informeTecnico;
        if (! $report || (! $report->horario_diurno && ! $report->horario_festivo) || (! $report->retribucion_tiempo && ! $report->retribucion_dinero) || blank($report->justificacion_tecnica) || blank($report->medidas_control)) {
            throw ValidationException::withMessages(['informe' => 'Debe completar los antecedentes obligatorios del Informe Técnico.']);
        }
        if ($tramite->horasExtra->funcionarios->isEmpty()) {
            throw ValidationException::withMessages(['funcionarios' => 'El trámite no tiene funcionarios.']);
        }
        foreach ($tramite->horasExtra->funcionarios as $participant) {
            if ($participant->review_status !== 'CONFORME' || ! $participant->planillaVigente || $participant->daytime_minutes === null || $participant->night_festive_minutes === null || $participant->total_minutes !== $participant->daytime_minutes + $participant->night_festive_minutes) {
                throw ValidationException::withMessages(['funcionarios' => 'Todos los funcionarios deben mantener planilla, horas finales y conformidad vigentes.']);
            }
        }
    }

    private function resolveTemplate(): DocumentoPlantilla
    {
        return DocumentoPlantilla::query()->where('codigo', 'HE_INFORME_TECNICO')->where('active', true)
            ->where(fn ($query) => $query->whereNull('valid_from')->orWhere('valid_from', '<=', today()))
            ->where(fn ($query) => $query->whereNull('valid_to')->orWhere('valid_to', '>=', today()))
            ->orderByDesc('version')->firstOrFail();
    }
}
