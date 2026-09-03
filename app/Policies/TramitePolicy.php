<?php

namespace App\Policies;

use App\Models\Tramite;
use App\Models\User;

class TramitePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canAny(['tramites.ver_propios', 'tramites.ver_unidad', 'tramites.ver_todos']);
    }

    public function view(User $user, Tramite $tramite): bool
    {
        return $user->can('tramites.ver_todos')
            || ($user->can('tramites.ver_propios') && $tramite->created_by === $user->id);
    }

    public function create(User $user): bool
    {
        return $user->can('tramites.crear');
    }
}
