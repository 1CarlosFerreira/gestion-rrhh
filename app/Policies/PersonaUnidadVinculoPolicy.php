<?php

namespace App\Policies;

use App\Models\PersonaUnidadVinculo;
use App\Models\UnidadOrganizacional;
use App\Models\User;
use App\Services\Alcances\AlcanceFuncionalUnidadResolver;

class PersonaUnidadVinculoPolicy
{
    public function __construct(private readonly AlcanceFuncionalUnidadResolver $alcance) {}

    public function viewAny(User $user): bool
    {
        return $user->active
            && $user->can('dotacion.ver')
            && ($user->can('dotacion.ver_todas') || $this->alcance->unidadesAutorizadas($user, today())->isNotEmpty());
    }

    public function view(User $user, PersonaUnidadVinculo $vinculo): bool
    {
        return $user->active
            && $user->can('dotacion.ver')
            && ($user->can('dotacion.ver_todas') || $this->alcance->incluye($user, $vinculo->unidad, today()));
    }

    public function create(User $user, UnidadOrganizacional $unidad): bool
    {
        return $this->puedeGestionarManualmente($user, $unidad);
    }

    public function update(User $user, PersonaUnidadVinculo $vinculo): bool
    {
        return ! $vinculo->esGeneradoPorTramite()
            && $this->puedeGestionarManualmente($user, $vinculo->unidad);
    }

    private function puedeGestionarManualmente(User $user, UnidadOrganizacional $unidad): bool
    {
        return $user->active
            && $user->hasRole('Administrador')
            && $user->can('dotacion.gestionar')
            && $unidad->activo;
    }
}
