<?php

namespace App\Contracts\Tramites;

use App\Models\Tramite;
use App\Models\TransicionEstado;
use App\Models\User;

interface TramiteTransitionGuard
{
    public function validate(Tramite $tramite, TransicionEstado $transicion, User $user, ?string $observation, array $metadata): void;
}
