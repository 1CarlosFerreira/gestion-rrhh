<?php

namespace App\Services\Reemplazos;

use App\Contracts\Tramites\TramiteTransitionGuard;
use App\Enums\ModalidadSolicitudContrato;
use App\Models\DocumentoGenerado;
use App\Models\Persona;
use App\Models\PersonaUnidadVinculo;
use App\Models\ReemplazoFormalizacion;
use App\Models\ReservaPersonaPeriodo;
use App\Models\RespaldoAfectacion;
use App\Models\TipoReemplazo;
use App\Models\Tramite;
use App\Models\TransicionEstado;
use App\Models\User;
use App\Services\Respaldos\RespaldosAptosSolicitudTransitoria;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ReemplazoTransitionGuard implements TramiteTransitionGuard
{
    public function __construct(private readonly ReemplazoService $reemplazos, private readonly ReemplazoWorkflow $workflow, private readonly RespaldosAptosSolicitudTransitoria $respaldosAptos) {}

    public function validate(Tramite $tramite, TransicionEstado $transicion, User $user, ?string $observation, array $metadata): void
    {
        if ($tramite->tipoTramite()->value('codigo') !== 'REEMPLAZO') {
            return;
        }
        $this->autorizar($tramite, $transicion, $user);

        if (in_array($transicion->codigo_accion, ['ENVIAR_A_GESTION_PERSONAS', 'REENVIAR_A_GESTION_PERSONAS'], true)) {
            $this->validarEnvio($tramite);
        }
        if ($transicion->codigo_accion === 'INICIAR_REVISION' && $tramite->solicitudContrato()->exists()) {
            $this->validarEnvio($tramite);
        }
        if ($transicion->codigo_accion === 'APROBAR_ANTECEDENTES') {
            $this->validarAprobacion($tramite, $user, $metadata);
        }
        if ($transicion->codigo_accion === 'GENERAR_DOCUMENTO') {
            $this->validarDocumentoGenerado($tramite, $metadata);
        }
        if ($transicion->codigo_accion === 'FORMALIZAR_REEMPLAZO') {
            $this->validarFormalizacion($tramite, $metadata);
        }
    }

    private function autorizar(Tramite $tramite, TransicionEstado $transicion, User $user): void
    {
        $ability = match ($transicion->codigo_accion) {
            'ENVIAR_A_GESTION_PERSONAS', 'REENVIAR_A_GESTION_PERSONAS' => 'editar-reemplazo',
            'INICIAR_REVISION', 'DEVOLVER_PARA_CORRECCION', 'APROBAR_ANTECEDENTES' => 'revisar-reemplazo',
            'GENERAR_DOCUMENTO' => 'generar-documento-reemplazo',
            'FORMALIZAR_REEMPLAZO' => 'formalizar-reemplazo',
            default => null,
        };

        if ($ability !== null) {
            Gate::forUser($user)->authorize($ability, $tramite);
        }
    }

    private function validarAprobacion(Tramite $tramite, User $user, array $metadata): void
    {
        $revision = $tramite->revisionReemplazo()->first();
        $errors = [];
        if (! $revision?->grado_eus) {
            $errors['grado_eus'] = 'El grado E.U.S. es obligatorio.';
        }
        if (! $revision?->clasificacion_area_id) {
            $errors['clasificacion_area_id'] = 'La clasificación de área es obligatoria.';
        }
        if ($revision?->cumple_normativa === null) {
            $errors['cumple_normativa'] = 'Debe informar el cumplimiento de normativa.';
        }
        if ($revision === null
            || (int) ($metadata['revision_id'] ?? 0) !== $revision->id
            || $revision->revisado_por !== $user->id
            || $revision->revisado_at === null) {
            $errors['revision'] = 'La aprobación debe registrar su revisión administrativa dentro de la misma operación.';
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        if ($tramite->solicitudContrato()->exists()) {
            $this->validarCompromisoTransitorio($tramite, $user, $metadata);
        }
    }

    private function validarCompromisoTransitorio(Tramite $tramite, User $user, array $metadata): void
    {
        $solicitud = $tramite->solicitudContrato()->first();
        $detalle = $tramite->reemplazo()->first();
        if ($solicitud?->modalidad !== ModalidadSolicitudContrato::TRANSITORIA) {
            throw ValidationException::withMessages(['solicitud' => 'La aprobación V3 requiere modalidad transitoria.']);
        }
        $afectacion = RespaldoAfectacion::query()
            ->whereKey((int) ($metadata['afectacion_id'] ?? 0))
            ->where('solicitud_contrato_id', $solicitud->id)
            ->where('respaldo_id', (int) ($metadata['respaldo_id'] ?? 0))
            ->where('respaldo_version_id', (int) ($metadata['respaldo_version_id'] ?? 0))
            ->where('comprometido_por', $user->id)
            ->whereNull('liberado_at')
            ->first();
        if ($afectacion === null) {
            throw ValidationException::withMessages(['respaldo' => 'La aprobación V3 requiere un compromiso vigente en la misma operación.']);
        }

        if ($detalle?->reemplazante_id === null) {
            if (array_key_exists('reserva_id', $metadata) || $afectacion->reservas()->whereNull('liberado_at')->exists()) {
                throw ValidationException::withMessages(['reserva' => 'Una solicitud sin candidato no debe crear una reserva.']);
            }

            return;
        }

        $reserva = ReservaPersonaPeriodo::query()
            ->whereKey((int) ($metadata['reserva_id'] ?? 0))
            ->where('afectacion_id', $afectacion->id)
            ->where('persona_id', $detalle->reemplazante_id)
            ->where('reservado_por', $user->id)
            ->whereNull('liberado_at')
            ->first();
        if ($reserva === null || $reserva->persona_id !== ($metadata['persona_reservada_id'] ?? null)
            || $reserva->fecha_desde->toDateString() !== ($metadata['periodo_reserva_desde'] ?? null)
            || $reserva->fecha_hasta->toDateString() !== ($metadata['periodo_reserva_hasta'] ?? null)) {
            throw ValidationException::withMessages(['reserva' => 'La aprobación V3 requiere una reserva vigente de la persona propuesta.']);
        }
    }

    private function validarDocumentoGenerado(Tramite $tramite, array $metadata): void
    {
        if ($tramite->solicitudContrato()->exists()) {
            throw ValidationException::withMessages(['documento' => 'La generación documental V3 requiere definir la autoridad de este acto.']);
        }

        $documento = DocumentoGenerado::query()
            ->with('adjunto')
            ->whereKey((int) ($metadata['documento_generado_id'] ?? 0))
            ->where('tramite_id', $tramite->id)
            ->where('status', 'VIGENTE')
            ->first();

        if ($documento === null
            || $documento->adjunto === null
            || $documento->adjunto->tramite_id !== $tramite->id
            || $documento->adjunto->status !== 'ACTIVO'
            || ! Storage::disk('private')->exists($documento->adjunto->storage_path)) {
            throw ValidationException::withMessages([
                'documento' => 'La transición requiere un documento generado vigente con evidencia activa.',
            ]);
        }
    }

    private function validarFormalizacion(Tramite $tramite, array $metadata): void
    {
        $formalizacionId = (int) ($metadata['formalizacion_id'] ?? 0);
        $documentoId = (int) ($metadata['documento_generado_id'] ?? 0);
        $vinculoId = (int) ($metadata['vinculo_dotacion_id'] ?? 0);

        $formalizacionValida = ReemplazoFormalizacion::query()
            ->whereKey($formalizacionId)
            ->where('tramite_id', $tramite->id)
            ->where('documento_generado_id', $documentoId)
            ->exists();
        $vinculoValido = PersonaUnidadVinculo::query()
            ->whereKey($vinculoId)
            ->where('origen_tramite_id', $tramite->id)
            ->exists();

        if (! $formalizacionValida || ! $vinculoValido) {
            throw ValidationException::withMessages([
                'formalizacion' => 'La transición requiere una formalización y un vínculo de dotación coherentes.',
            ]);
        }
    }

    private function validarEnvio(Tramite $tramite): void
    {
        $detalle = $tramite->reemplazo;
        $solicitud = $tramite->solicitudContrato;
        $esV3 = $solicitud !== null;
        $errors = [];
        $campos = ['funcionario_id', 'tipo_reemplazo_id', 'fecha_funcionario_desde', 'fecha_funcionario_hasta', 'justificacion'];
        if (! $esV3 || $detalle?->reemplazante_id !== null) {
            $campos = [...$campos, 'reemplazante_id', 'reemplazante_estamento_id', 'reemplazante_calidad_contractual_id', 'reemplazante_cargo_funcion', 'fecha_reemplazante_desde', 'fecha_reemplazante_hasta'];
        }
        foreach ($campos as $field) {
            if (blank($detalle?->{$field})) {
                $errors[$field] = 'Este campo es obligatorio para enviar.';
            }
        }
        if ($esV3 && ($solicitud->modalidad !== ModalidadSolicitudContrato::TRANSITORIA
            || $solicitud->unidad_solicitante_id !== $tramite->unidad_organizacional_id
            || $solicitud->unidad_origen_id === null || $solicitud->unidad_destino_id === null)) {
            $errors['solicitud'] = 'El contexto transitorio está incompleto o no corresponde al trámite.';
        }
        if ($esV3 && $detalle?->reemplazante_id !== null && ! Persona::query()->whereKey($detalle->reemplazante_id)->where('active', true)->exists()) {
            $errors['reemplazante_id'] = 'La persona propuesta debe estar activa.';
        }
        if ($esV3 && $detalle?->reemplazante_id === null && collect(['reemplazante_estamento_id', 'reemplazante_profesion_id', 'reemplazante_calidad_contractual_id', 'reemplazante_cargo_funcion', 'fecha_reemplazante_desde', 'fecha_reemplazante_hasta'])->contains(fn ($campo) => filled($detalle?->{$campo}))) {
            $errors['reemplazante_id'] = 'Quite los antecedentes de cobertura si aún no hay persona propuesta.';
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
        if ($esV3 && $this->respaldosAptos->obtener($solicitud, $detalle)->isEmpty()) {
            throw ValidationException::withMessages(['respaldo' => 'Registre un respaldo vigente que cubra al funcionario y su período antes de enviar.']);
        }
        if (! TipoReemplazo::query()->whereKey($detalle->tipo_reemplazo_id)->where('activo', true)->exists()) {
            throw ValidationException::withMessages(['tipo_reemplazo_id' => 'El tipo de reemplazo debe estar activo.']);
        }
        $unidadOrigenId = $tramite->solicitudContrato?->unidad_origen_id ?? $tramite->unidad_organizacional_id;
        $pertenece = Persona::query()->whereKey($detalle->funcionario_id)->where('active', true)->whereHas('vinculosDotacion', fn ($q) => $q->where('unidad_organizacional_id', $unidadOrigenId)->vigentesEn($detalle->fecha_funcionario_desde))->exists();
        if (! $pertenece) {
            throw ValidationException::withMessages(['funcionario_id' => 'El funcionario debe pertenecer a la dotación vigente de la unidad.']);
        }
        $this->reemplazos->validar($detalle->getAttributes());
        if ($this->reemplazos->existeSuperposicion($detalle->funcionario_id, $detalle->fecha_funcionario_desde, $detalle->fecha_funcionario_hasta, $detalle->id, fn ($q) => $this->workflow->filtrarActivos($q))) {
            throw ValidationException::withMessages(['fecha_funcionario_desde' => 'El funcionario ya posee otro reemplazo activo superpuesto.']);
        }
        if (Schema::hasTable('requisitos_documentales')) {
            $required = DB::table('requisitos_documentales')->where('tipo_tramite_id', $tramite->tipo_tramite_id)->where('obligatorio', true)->where('active', true)->where(fn ($q) => $q->whereNull('tipo_reemplazo_id')->orWhere('tipo_reemplazo_id', $detalle->tipo_reemplazo_id))->pluck('tipo_documento_id');
            $present = $tramite->adjuntos()->activos()->whereIn('tipo_documento_id', $required)->pluck('tipo_documento_id');
            if ($required->diff($present)->isNotEmpty()) {
                throw ValidationException::withMessages(['documentos' => 'Faltan documentos obligatorios configurados.']);
            }
        }
    }
}
