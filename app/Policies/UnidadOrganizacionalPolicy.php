<?php

namespace App\Policies;

use App\Models\UnidadOrganizacional;
use App\Models\User;

class UnidadOrganizacionalPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('estructura_organizacional.ver');
    }

    public function view(User $user, UnidadOrganizacional $unidad): bool
    {
        return $user->can('estructura_organizacional.ver');
    }

    public function create(User $user): bool
    {
        return $user->can('estructura_organizacional.gestionar');
    }

    public function update(User $user, UnidadOrganizacional $unidad): bool
    {
        return $user->can('estructura_organizacional.gestionar');
    }
}
