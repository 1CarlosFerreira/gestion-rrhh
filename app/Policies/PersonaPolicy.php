<?php

namespace App\Policies;

use App\Models\Persona;
use App\Models\User;

class PersonaPolicy
{
    public function viewFicha(User $user, Persona $persona): bool
    {
        if (! $user->can('dotacion.ver')) {
            return false;
        }
        if ($user->can('tramites.ver_todos')) {
            return true;
        }

        return $persona->vinculos()->whereIn('unidad_servicio_id', $user->unidadesHabilitadas()->select('unidades_servicios.id'))->exists();
    }
}
