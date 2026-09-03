<?php

namespace App\Policies;

use App\Models\User;
use App\Models\UserUnidadAcceso;

class UserUnidadAccesoPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('accesos_operativos.ver');
    }

    public function create(User $user): bool
    {
        return $user->can('accesos_operativos.gestionar');
    }

    public function update(User $user, UserUnidadAcceso $acceso): bool
    {
        return $user->can('accesos_operativos.gestionar');
    }
}
