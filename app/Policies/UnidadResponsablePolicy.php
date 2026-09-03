<?php

namespace App\Policies;

use App\Models\UnidadResponsable;
use App\Models\User;

class UnidadResponsablePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('responsabilidades.ver');
    }

    public function create(User $user): bool
    {
        return $user->can('responsabilidades.gestionar');
    }

    public function update(User $user, UnidadResponsable $responsabilidad): bool
    {
        return $user->can('responsabilidades.gestionar');
    }
}
