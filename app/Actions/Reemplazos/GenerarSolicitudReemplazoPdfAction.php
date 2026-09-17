<?php

namespace App\Actions\Reemplazos;

use App\Actions\Tramites\TransicionarTramite;
use App\Models\DocumentoGenerado;
use App\Models\DocumentoPlantilla;
use App\Models\PersonaUnidadVinculo;
use App\Models\TipoDocumento;
use App\Models\Tramite;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class GenerarSolicitudReemplazoPdfAction
{
    public function __construct(private readonly TransicionarTramite $transicionar) {}

    public function execute(Tramite $tramite, User $user): DocumentoGenerado
    {
        Gate::forUser($user)->authorize('generar-documento-reemplazo', $tramite);
        $storedPath = null;
        try {
            return DB::transaction(function () use ($tramite, $user, &$storedPath): DocumentoGenerado {
                $locked = Tramite::query()->lockForUpdate()->with(['tipoTramite', 'estadoTramite', 'unidadOrganizacional', 'creador', 'reemplazo.funcionario', 'reemplazo.reemplazante', 'reemplazo.reemplazanteEstamento', 'reemplazo.reemplazanteProfesion', 'reemplazo.reemplazanteCalidadContractual', 'reemplazo.tipoReemplazo', 'revisionReemplazo.clasificacionArea'])->findOrFail($tramite->id);
                $this->validar($locked);
                $tipo = TipoDocumento::query()->where('codigo', 'DOCUMENTO_GENERADO')->where('active', true)->firstOrFail();
                if (DocumentoGenerado::query()->where('tramite_id', $locked->id)->where('tipo_documento_id', $tipo->id)->exists()) {
                    throw ValidationException::withMessages(['documento' => 'La solicitud ya posee un documento generado. La regeneración queda pendiente.']);
                }
                $plantilla = DocumentoPlantilla::query()->where('codigo', 'REEMPLAZO_SOLICITUD_PDF')->where('active', true)->orderByDesc('version')->firstOrFail();
                $snapshot = $this->snapshot($locked, $plantilla);
                $bytes = Pdf::loadView($plantilla->template_path, ['snapshot' => $snapshot])->setPaper('a4')->output();
                if (! str_starts_with($bytes, '%PDF')) {
                    throw new \RuntimeException('El generador no produjo un PDF válido.');
                }
                $storedName = Str::ulid().'.pdf';
                $storedPath = 'tramites/'.$locked->public_id.'/documentos/'.$storedName;
                Storage::disk('private')->put($storedPath, $bytes);
                if (! Storage::disk('private')->exists($storedPath)) {
                    throw new \RuntimeException('No fue posible almacenar el documento generado.');
                }
                $hash = hash('sha256', Storage::disk('private')->get($storedPath));
                $adjunto = $locked->adjuntos()->create(['tipo_documento_id' => $tipo->id, 'uploaded_by' => $user->id, 'original_name' => 'Solicitud_Reemplazo_'.$locked->codigo.'.pdf', 'stored_name' => $storedName, 'storage_path' => $storedPath, 'mime_type' => 'application/pdf', 'size_bytes' => strlen($bytes), 'sha256' => $hash, 'version' => 1, 'status' => 'ACTIVO']);
                $documento = DocumentoGenerado::query()->create(['tramite_id' => $locked->id, 'documento_plantilla_id' => $plantilla->id, 'tipo_documento_id' => $tipo->id, 'adjunto_id' => $adjunto->id, 'version' => 1, 'generated_by' => $user->id, 'generated_at' => now(), 'status' => 'VIGENTE', 'metadata' => $snapshot]);
                $this->transicionar->execute($locked, 'GENERAR_DOCUMENTO', $user, null, ['documento_generado_id' => $documento->id, 'version' => 1, 'sha256' => $hash]);

                return $documento->load(['adjunto', 'plantilla', 'generadoPor']);
            });
        } catch (Throwable $exception) {
            if ($storedPath !== null) {
                Storage::disk('private')->delete($storedPath);
            }
            throw $exception;
        }
    }

    private function validar(Tramite $tramite): void
    {
        if ($tramite->tipoTramite?->codigo !== 'REEMPLAZO' || $tramite->estadoTramite?->codigo !== 'LISTA_GENERAR_DOCUMENTO') {
            throw ValidationException::withMessages(['tramite' => 'La solicitud debe estar lista para generar documento.']);
        }
        $detalle = $tramite->reemplazo;
        $revision = $tramite->revisionReemplazo;
        $errors = [];
        if ($tramite->unidadOrganizacional === null) {
            $errors['unidad'] = 'La unidad es obligatoria.';
        }
        foreach (['funcionario_id', 'reemplazante_id', 'tipo_reemplazo_id', 'fecha_funcionario_desde', 'fecha_funcionario_hasta', 'fecha_reemplazante_desde', 'fecha_reemplazante_hasta', 'justificacion'] as $field) {
            if (blank($detalle?->{$field})) {
                $errors[$field] = 'Este antecedente es obligatorio para generar el documento.';
            }
        }
        if (! $revision?->grado_eus) {
            $errors['grado_eus'] = 'El grado E.U.S. es obligatorio.';
        }
        if (! $revision?->clasificacion_area_id) {
            $errors['clasificacion_area_id'] = 'La clasificación de área es obligatoria.';
        }
        if ($revision?->cumple_normativa === null) {
            $errors['cumple_normativa'] = 'El cumplimiento normativo es obligatorio.';
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function snapshot(Tramite $tramite, DocumentoPlantilla $plantilla): array
    {
        $detalle = $tramite->reemplazo;
        $vinculo = PersonaUnidadVinculo::query()->with(['estamento', 'profesion'])->where('persona_id', $detalle->funcionario_id)->where('unidad_organizacional_id', $tramite->unidad_organizacional_id)->vigentesEn($detalle->fecha_funcionario_desde)->orderByDesc('vigente_desde')->first();
        $conPropuesta = $detalle->tieneAntecedentesLaboralesPropuestos();
        $reemplazante = ['nombre' => $detalle->reemplazante->nombre_completo, 'rut' => $detalle->reemplazante->rut, 'desde' => $detalle->fecha_reemplazante_desde->toDateString(), 'hasta' => $detalle->fecha_reemplazante_hasta->toDateString()];
        if ($conPropuesta) {
            $reemplazante = [...$reemplazante, 'estamento' => $detalle->reemplazanteEstamento->nombre, 'profesion' => $detalle->reemplazanteProfesion?->nombre, 'calidad_contractual' => $detalle->reemplazanteCalidadContractual->nombre, 'cargo_funcion' => $detalle->reemplazante_cargo_funcion];
        }

        return [
            'schema_version' => $conPropuesta ? 2 : 1,
            'plantilla' => ['codigo' => $plantilla->codigo, 'version' => $plantilla->version, 'sha256' => $plantilla->sha256],
            'generado_at' => now()->toIso8601String(),
            'tramite' => ['codigo' => $tramite->codigo, 'unidad' => $tramite->unidadOrganizacional->nombre, 'creador' => $tramite->creador?->name],
            'funcionario' => ['nombre' => $detalle->funcionario->nombre_completo, 'rut' => $detalle->funcionario->rut, 'estamento' => $vinculo?->estamento?->nombre, 'profesion' => $vinculo?->profesion?->nombre, 'cargo' => $vinculo?->cargo_funcion, 'desde' => $detalle->fecha_funcionario_desde->toDateString(), 'hasta' => $detalle->fecha_funcionario_hasta->toDateString()],
            'reemplazante' => $reemplazante,
            'solicitud' => ['tipo' => $detalle->tipoReemplazo->nombre, 'justificacion' => $detalle->justificacion, 'dias_totales' => $detalle->diasFuncionario(), 'dias_cubiertos' => $detalle->diasReemplazante(), 'dias_sin_cobertura' => $detalle->diasSinCobertura()],
            'revision' => ['grado_eus' => $tramite->revisionReemplazo->grado_eus, 'clasificacion_area' => $tramite->revisionReemplazo->clasificacionArea->nombre, 'cumple_normativa' => $tramite->revisionReemplazo->cumple_normativa, 'observacion' => $tramite->revisionReemplazo->observacion_administrativa],
            'destinatario' => 'SUBDIRECCIÓN DE GESTIÓN Y DESARROLLO DE LAS PERSONAS',
        ];
    }
}
