<?php

namespace App\Actions\Reemplazos;

use App\Contracts\Tramites\TramiteTransitionGuard;
use App\Models\Tramite;
use App\Models\TransicionEstado;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReemplazoTransitionGuard implements TramiteTransitionGuard
{
    public function validate(Tramite $tramite, TransicionEstado $transicion, User $user, ?string $observation, array $metadata): void
    {
        if ($tramite->tipoTramite()->value('codigo') !== 'REEMPLAZO') {
            return;
        }
        $detail = $tramite->reemplazo()->first();
        if (! $detail) {
            return;
        }
        if ($transicion->codigo_accion === 'ENVIAR_A_GESTION_PERSONAS') {
            $errors = $this->sendErrors($tramite);
            if ($errors) {
                throw ValidationException::withMessages($errors);
            }
        }
        if ($transicion->codigo_accion === 'COMPLETAR_REVISION') {
            $revision = $tramite->revisionReemplazo()->first();
            $errors = [];
            if (! $revision?->clasificacion_area_id) {
                $errors['clasificacion_area_id'] = 'La clasificación es obligatoria.';
            }
            if ($revision?->cumple_normativa === null) {
                $errors['cumple_normativa'] = 'Debe informar si cumple normativa.';
            }
            if ($errors) {
                throw ValidationException::withMessages($errors);
            }
        }
    }

    public function sendErrors(Tramite $tramite): array
    {
        $detail = $tramite->reemplazo()->first();
        $errors = [];
        foreach (['tipo_reemplazo_id', 'reemplazante_id', 'estamento_id', 'justificacion', 'fecha_inicio', 'fecha_termino'] as $field) {
            if (blank($detail?->{$field})) {
                $errors[$field] = 'Este campo es obligatorio antes de enviar.';
            }
        }
        if ($detail && blank($detail->profesion_id) && blank($detail->cargo_texto)) {
            $errors['cargo_texto'] = 'Debe indicar profesión o cargo.';
        }
        if ($detail?->fecha_inicio && $detail?->fecha_termino && $detail->fecha_termino->lt($detail->fecha_inicio)) {
            $errors['fecha_termino'] = 'La fecha de término debe ser posterior o igual al inicio.';
        }
        $requiredTypes = DB::table('requisitos_documentales')
            ->where('tipo_tramite_id', $tramite->tipo_tramite_id)
            ->where('obligatorio', true)->where('active', true)
            ->where(fn ($query) => $query->whereNull('tipo_reemplazo_id')->orWhere('tipo_reemplazo_id', $detail->tipo_reemplazo_id))
            ->where(fn ($query) => $query->whereNull('valid_from')->orWhere('valid_from', '<=', now()->toDateString()))
            ->where(fn ($query) => $query->whereNull('valid_to')->orWhere('valid_to', '>=', now()->toDateString()))
            ->pluck('tipo_documento_id');
        if ($requiredTypes->isNotEmpty()) {
            $presentTypes = $tramite->adjuntos()->activos()->whereIn('tipo_documento_id', $requiredTypes)->pluck('tipo_documento_id');
            if ($requiredTypes->diff($presentTypes)->isNotEmpty()) {
                $errors['documentos'] = 'Faltan documentos obligatorios configurados.';
            }
        }

        return $errors;
    }
}
