<?php

namespace App\Policies;

use App\Models\PersonaUnidadVinculo;
use App\Models\UnidadOrganizacional;
use App\Models\User;
use App\Services\Accesos\AccesoOperativoService;
use App\Services\Alcances\AlcanceFuncionalUnidadResolver;

class PersonaUnidadVinculoPolicy
{
    public function __construct(
        private readonly AccesoOperativoService $accesos,
        private readonly AlcanceFuncionalUnidadResolver $alcance,
    ) {}

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
        return $this->puede($user, 'dotacion.gestionar', $unidad);
    }

    public function update(User $user, PersonaUnidadVinculo $vinculo): bool
    {
        return ! $vinculo->esGeneradoPorTramite()
            && $this->puede($user, 'dotacion.gestionar', $vinculo->unidad);
    }

    private function puede(User $user, string $permiso, UnidadOrganizacional $unidad): bool
    {
        return $this->accesos->tienePermisoYAcceso($user, $permiso, $unidad, today());
    }
}
