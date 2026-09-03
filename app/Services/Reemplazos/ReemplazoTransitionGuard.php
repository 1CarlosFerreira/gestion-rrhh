<?php

namespace App\Services\Reemplazos;

use App\Contracts\Tramites\TramiteTransitionGuard;
use App\Models\Persona;
use App\Models\TipoReemplazo;
use App\Models\Tramite;
use App\Models\TransicionEstado;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class ReemplazoTransitionGuard implements TramiteTransitionGuard
{
    public function __construct(private readonly ReemplazoService $reemplazos, private readonly ReemplazoWorkflow $workflow) {}

    public function validate(Tramite $tramite, TransicionEstado $transicion, User $user, ?string $observation, array $metadata): void
    {
        if ($tramite->tipoTramite()->value('codigo') !== 'REEMPLAZO') {
            return;
        }
        if (in_array($transicion->codigo_accion, ['ENVIAR_A_GESTION_PERSONAS', 'REENVIAR_A_GESTION_PERSONAS'], true)) {
            $this->validarEnvio($tramite);
        }
        if ($transicion->codigo_accion === 'APROBAR_ANTECEDENTES') {
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
            if ($errors) {
                throw ValidationException::withMessages($errors);
            }
        }
    }

    private function validarEnvio(Tramite $tramite): void
    {
        $detalle = $tramite->reemplazo;
        $errors = [];
        foreach (['funcionario_id', 'tipo_reemplazo_id', 'fecha_funcionario_desde', 'fecha_funcionario_hasta', 'reemplazante_id', 'fecha_reemplazante_desde', 'fecha_reemplazante_hasta', 'justificacion'] as $field) {
            if (blank($detalle?->{$field})) {
                $errors[$field] = 'Este campo es obligatorio para enviar.';
            }
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
        if (! TipoReemplazo::query()->whereKey($detalle->tipo_reemplazo_id)->where('activo', true)->exists()) {
            throw ValidationException::withMessages(['tipo_reemplazo_id' => 'El tipo de reemplazo debe estar activo.']);
        }
        $pertenece = Persona::query()->whereKey($detalle->funcionario_id)->where('active', true)->whereHas('vinculosDotacion', fn ($q) => $q->where('unidad_organizacional_id', $tramite->unidad_organizacional_id)->vigentesEn($detalle->fecha_funcionario_desde))->exists();
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
