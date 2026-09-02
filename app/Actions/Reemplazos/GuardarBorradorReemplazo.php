<?php

namespace App\Actions\Reemplazos;

use App\Models\Persona;
use App\Models\PersonaUnidadVinculo;
use App\Models\Tramite;
use App\Models\User;
use App\Support\Rut\Rut;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class GuardarBorradorReemplazo
{
    public function execute(Tramite $tramite, array $data, User $user): Tramite
    {
        if (! $user->can('reemplazos.crear') || Gate::forUser($user)->denies('view', $tramite) || ! in_array($tramite->estadoTramite->codigo, ['BORRADOR', 'DEVUELTA_CORRECCION'], true)) {
            throw new AuthorizationException('El reemplazo no se puede editar en su estado actual.');
        }
        $funcionarioId = array_key_exists('funcionario_id', $data)
            ? $data['funcionario_id']
            : $tramite->reemplazo->funcionario_id;

        if ($funcionarioId) {
            $today = now()->toDateString();
            $vinculo = PersonaUnidadVinculo::query()->where('persona_id', $funcionarioId)->where('unidad_servicio_id', $tramite->unidad_servicio_id)->where('status', 'ACTIVO')->where(fn ($query) => $query->whereNull('start_date')->orWhere('start_date', '<=', $today))->where(fn ($query) => $query->whereNull('end_date')->orWhere('end_date', '>=', $today))->first();
            if (! $vinculo || (($data['funcionario_vinculo_id'] ?? null) && (int) $data['funcionario_vinculo_id'] !== $vinculo->id)) {
                throw ValidationException::withMessages(['funcionario_id' => 'El funcionario debe pertenecer a la dotación vigente de la unidad del trámite.']);
            }
            $data['funcionario_vinculo_id'] = $vinculo->id;
        }

        if (($data['reemplazante_id'] ?? null) && ($data['nuevo_reemplazante_rut'] ?? null)) {
            throw ValidationException::withMessages(['reemplazante_id' => 'Seleccione una persona existente o registre un nuevo reemplazante, no ambas opciones.']);
        }

        $reemplazanteId = $data['reemplazante_id'] ?? null;
        if (! $reemplazanteId && ($data['nuevo_reemplazante_rut'] ?? null)) {
            $rut = Rut::normalize($data['nuevo_reemplazante_rut']);
            $persona = Persona::query()->where('rut', $rut)->first();

            if ($persona && $persona->id === (int) $funcionarioId) {
                throw ValidationException::withMessages(['nuevo_reemplazante_rut' => 'Esta persona corresponde al funcionario que está siendo reemplazado y no puede registrarse como reemplazante.']);
            }

            $persona ??= Persona::query()->create([
                'rut' => $rut,
                'nombres' => $data['nuevo_reemplazante_nombres'],
                'apellido_paterno' => $data['nuevo_reemplazante_apellido_paterno'] ?? null,
                'apellido_materno' => $data['nuevo_reemplazante_apellido_materno'] ?? null,
                'active' => true,
            ]);
            $reemplazanteId = $persona->id;
        }

        if ($funcionarioId && $reemplazanteId && (int) $funcionarioId === (int) $reemplazanteId) {
            throw ValidationException::withMessages(['reemplazante_id' => 'El reemplazante propuesto no puede ser la misma persona que el funcionario a reemplazar.']);
        }

        $absence = $tramite->reemplazo->ausencia()->firstOrFail();
        $absenceFields = [];
        foreach (['funcionario_id', 'tipo_reemplazo_id', 'justificacion'] as $field) {
            if (array_key_exists($field, $data)) {
                $absenceFields[$field] = $data[$field];
            }
        }
        if (array_key_exists('fecha_inicio_ausencia', $data)) {
            $absenceFields['fecha_inicio'] = $data['fecha_inicio_ausencia'];
        }
        if (array_key_exists('fecha_termino_ausencia', $data)) {
            $absenceFields['fecha_termino'] = $data['fecha_termino_ausencia'];
        }
        // Compatibilidad para llamadas internas anteriores a la separación de periodos.
        if (! array_key_exists('fecha_inicio_ausencia', $data) && ! $absence->fecha_inicio && array_key_exists('fecha_inicio', $data)) {
            $absenceFields['fecha_inicio'] = $data['fecha_inicio'];
        }
        if (! array_key_exists('fecha_termino_ausencia', $data) && ! $absence->fecha_termino && array_key_exists('fecha_termino', $data)) {
            $absenceFields['fecha_termino'] = $data['fecha_termino'];
        }
        $absence->update($absenceFields);

        $fields = collect($data)->only(['tipo_reemplazo_id', 'funcionario_id', 'funcionario_vinculo_id', 'estamento_id', 'profesion_id', 'cargo_texto', 'justificacion', 'fecha_inicio', 'fecha_termino'])->all();
        if (array_key_exists('reemplazante_id', $data) || ($data['nuevo_reemplazante_rut'] ?? null)) {
            $fields['reemplazante_id'] = $reemplazanteId;
        }
        $tramite->reemplazo->update($fields);

        return $tramite->refresh();
    }
}
