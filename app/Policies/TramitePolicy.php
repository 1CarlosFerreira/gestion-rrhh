<?php

namespace App\Policies;

use App\Models\Tramite;
use App\Models\User;
use App\Services\Accesos\AccesoOperativoService;

class TramitePolicy
{
    public function __construct(private readonly AccesoOperativoService $accesos) {}

    public function viewAny(User $user): bool
    {
        return $user->canAny(['tramites.ver_propios', 'tramites.ver_unidad', 'tramites.ver_todos']);
    }

    public function view(User $user, Tramite $tramite): bool
    {
        return $user->can('tramites.ver_todos')
            || ($user->can('tramites.ver_propios') && $tramite->created_by === $user->id)
            || ($tramite->tipoTramite?->codigo === 'REEMPLAZO' && $tramite->unidadOrganizacional !== null && $this->accesos->tienePermisoYAcceso($user, 'reemplazos.crear', $tramite->unidadOrganizacional, today()));
    }

    public function create(User $user): bool
    {
        return $user->can('tramites.crear');
    }
}
