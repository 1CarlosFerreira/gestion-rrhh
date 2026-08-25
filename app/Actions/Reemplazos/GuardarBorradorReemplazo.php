<?php

namespace App\Actions\Reemplazos;

use App\Models\Persona;
use App\Models\PersonaUnidadVinculo;
use App\Models\Tramite;
use App\Models\User;
use App\Support\Rut\Rut;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

class GuardarBorradorReemplazo
{
    public function execute(Tramite $tramite, array $data, User $user): Tramite
    {
        if (! $user->can('reemplazos.crear') || Gate::forUser($user)->denies('view', $tramite) || ! in_array($tramite->estadoTramite->codigo, ['BORRADOR', 'DEVUELTA_CORRECCION'], true)) {
            throw new AuthorizationException('El reemplazo no se puede editar en su estado actual.');
        }
        if (($data['funcionario_vinculo_id'] ?? null) && ! PersonaUnidadVinculo::query()->whereKey($data['funcionario_vinculo_id'])->where('persona_id', $data['funcionario_id'] ?? 0)->exists()) {
            throw new \InvalidArgumentException('El vínculo no corresponde al funcionario seleccionado.');
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
