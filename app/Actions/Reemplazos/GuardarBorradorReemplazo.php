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
        if ($data['funcionario_id'] ?? null) {
            $today = now()->toDateString();
            $vinculo = PersonaUnidadVinculo::query()->where('persona_id', $data['funcionario_id'])->where('unidad_servicio_id', $tramite->unidad_servicio_id)->where('status', 'ACTIVO')->where(fn ($query) => $query->whereNull('start_date')->orWhere('start_date', '<=', $today))->where(fn ($query) => $query->whereNull('end_date')->orWhere('end_date', '>=', $today))->first();
            if (! $vinculo || (($data['funcionario_vinculo_id'] ?? null) && (int) $data['funcionario_vinculo_id'] !== $vinculo->id)) {
                throw ValidationException::withMessages(['funcionario_id' => 'El funcionario debe pertenecer a la dotación vigente de la unidad del trámite.']);
            }
            $data['funcionario_vinculo_id'] = $vinculo->id;
        }
        $reemplazanteId = $data['reemplazante_id'] ?? null;
        if (! $reemplazanteId && ($data['nuevo_reemplazante_rut'] ?? null)) {
            $persona = Persona::query()->firstOrCreate(['rut' => Rut::normalize($data['nuevo_reemplazante_rut'])], [
                'nombres' => $data['nuevo_reemplazante_nombres'],
                'apellido_paterno' => $data['nuevo_reemplazante_apellido_paterno'] ?? null,
                'apellido_materno' => $data['nuevo_reemplazante_apellido_materno'] ?? null,
                'active' => true,
            ]);
            $reemplazanteId = $persona->id;
        }
        $fields = collect($data)->only(['tipo_reemplazo_id', 'funcionario_id', 'funcionario_vinculo_id', 'estamento_id', 'profesion_id', 'cargo_texto', 'justificacion', 'fecha_inicio', 'fecha_termino'])->all();
        if (array_key_exists('reemplazante_id', $data) || ($data['nuevo_reemplazante_rut'] ?? null)) {
            $fields['reemplazante_id'] = $reemplazanteId;
        }
        $tramite->reemplazo->update($fields);

        return $tramite->refresh();
    }
}
