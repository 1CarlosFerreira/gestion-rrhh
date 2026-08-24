<?php

namespace App\Policies;

use App\Models\UnidadServicio;
use App\Models\User;

class UnidadServicioPolicy
{
    public function viewDotacion(User $user, UnidadServicio $unidad): bool
    {
        if ($user->can('tramites.ver_todos')) {
            return true;
        }

        return $user->can('dotacion.ver') && $user->unidadesHabilitadas()->whereKey($unidad->id)->exists();
    }
}
