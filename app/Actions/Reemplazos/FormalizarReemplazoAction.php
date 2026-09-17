<?php

namespace App\Actions\Reemplazos;

use App\Actions\Tramites\Adjuntos\CargarAdjunto;
use App\Actions\Tramites\TransicionarTramite;
use App\Models\DocumentoGenerado;
use App\Models\ReemplazoFormalizacion;
use App\Models\Tramite;
use App\Models\TramiteAdjunto;
use App\Models\User;
use App\Services\Dotacion\DotacionService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class FormalizarReemplazoAction
{
    public function __construct(
        private readonly CargarAdjunto $cargarAdjunto,
        private readonly DotacionService $dotacion,
        private readonly TransicionarTramite $transicionar,
    ) {}

    public function execute(Tramite $tramite, array $datos, User $actor, ?UploadedFile $documentoFinal = null): ReemplazoFormalizacion
    {
        Gate::forUser($actor)->authorize('formalizar-reemplazo', $tramite);
        $adjuntoCreado = null;

        try {
            return DB::transaction(function () use ($tramite, $datos, $actor, $documentoFinal, &$adjuntoCreado): ReemplazoFormalizacion {
                $tramite = Tramite::query()->with(['estadoTramite', 'reemplazo', 'revisionReemplazo'])->lockForUpdate()->findOrFail($tramite->id);
                $existente = $tramite->formalizacionReemplazo()->first();
                if ($existente !== null) {
                    if (! $tramite->vinculoDotacion()->exists()) {
                        throw ValidationException::withMessages(['formalizacion' => 'La formalización existente no posee su vínculo de dotación.']);
                    }

                    return $existente;
                }
                if ($tramite->estadoTramite?->codigo !== 'DOCUMENTO_GENERADO') {
                    throw ValidationException::withMessages(['tramite' => 'El trámite debe tener su documento generado antes de formalizar.']);
                }
                $documento = DocumentoGenerado::query()->where('tramite_id', $tramite->id)->where('status', 'VIGENTE')->lockForUpdate()->first();
                if ($documento === null) {
                    throw ValidationException::withMessages(['documento' => 'No existe un documento generado vigente para formalizar.']);
                }
                $detalle = $tramite->reemplazo;
                if ($detalle === null || $detalle->reemplazante_id === null || $detalle->fecha_reemplazante_desde === null || $detalle->fecha_reemplazante_hasta === null) {
                    throw ValidationException::withMessages(['tramite' => 'El reemplazo no posee los datos efectivos necesarios para formalizar.']);
                }

                $esLegado = ! $detalle->tieneAntecedentesLaboralesPropuestos();
                $estamentoId = $esLegado ? $datos['estamento_id'] : $detalle->reemplazante_estamento_id;
                $profesionId = $esLegado ? ($datos['profesion_id'] ?? null) : $detalle->reemplazante_profesion_id;
                $calidadId = $esLegado ? $datos['calidad_contractual_id'] : $detalle->reemplazante_calidad_contractual_id;
                $cargo = preg_replace('/\s+/u', ' ', trim($esLegado ? $datos['cargo_funcion'] : $detalle->reemplazante_cargo_funcion));
                $formalizacion = $tramite->formalizacionReemplazo()->create([
                    'documento_generado_id' => $documento->id,
                    'estamento_id' => $estamentoId,
                    'profesion_id' => $profesionId,
                    'calidad_contractual_id' => $calidadId,
                    'cargo_funcion' => $cargo,
                    'cargo_funcion_normalizado' => mb_strtolower($cargo),
                    'grado_eus' => $tramite->revisionReemplazo?->grado_eus,
                    'identificador_externo' => filled($datos['identificador_externo'] ?? null) ? trim($datos['identificador_externo']) : null,
                    'observacion' => filled($datos['observacion'] ?? null) ? trim($datos['observacion']) : null,
                    'formalizado_por' => $actor->id,
                    'formalizado_at' => now(),
                ]);

                if ($documentoFinal !== null) {
                    $adjuntoCreado = $this->cargarAdjunto->execute($tramite, $documentoFinal, $actor, null, $detalle->reemplazante_id, permission: 'reemplazos.formalizar');
                    $formalizacion->update(['adjunto_id' => $adjuntoCreado->id]);
                }

                $vinculo = $this->dotacion->crearDesdeDocumentoFirmado($tramite, [
                    'persona_id' => $detalle->reemplazante_id,
                    'unidad_organizacional_id' => $tramite->unidad_organizacional_id,
                    'estamento_id' => $formalizacion->estamento_id,
                    'profesion_id' => $formalizacion->profesion_id,
                    'calidad_contractual_id' => $formalizacion->calidad_contractual_id,
                    'cargo_funcion' => $formalizacion->cargo_funcion,
                    'grado_eus' => $formalizacion->grado_eus,
                    'vigente_desde' => $detalle->fecha_reemplazante_desde,
                    'vigente_hasta' => $detalle->fecha_reemplazante_hasta,
                    'observacion' => $formalizacion->observacion,
                ], $actor);

                $this->transicionar->execute($tramite, 'FORMALIZAR_REEMPLAZO', $actor, metadata: [
                    'formalizacion_id' => $formalizacion->id,
                    'documento_generado_id' => $documento->id,
                    'vinculo_dotacion_id' => $vinculo->id,
                    'adjunto_id' => $formalizacion->adjunto_id,
                ]);

                return $formalizacion->refresh();
            });
        } catch (Throwable $exception) {
            if ($adjuntoCreado instanceof TramiteAdjunto) {
                Storage::disk('private')->delete($adjuntoCreado->storage_path);
            }
            throw $exception;
        }
    }
}
