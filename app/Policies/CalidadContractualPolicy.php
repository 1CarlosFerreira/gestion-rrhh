<?php

namespace App\Policies;

use App\Models\CalidadContractual;
use App\Models\User;

class CalidadContractualPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('calidades_contractuales.ver');
    }

    public function create(User $user): bool
    {
        return $user->can('calidades_contractuales.gestionar');
    }

    public function update(User $user, CalidadContractual $calidad): bool
    {
        return $user->can('calidades_contractuales.gestionar');
    }
}
