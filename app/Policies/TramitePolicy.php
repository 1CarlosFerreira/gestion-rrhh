<?php

namespace App\Policies;

use App\Models\Tramite;
use App\Models\User;
use App\Services\Accesos\AccesoOperativoService;
use App\Services\Reemplazos\AlcanceSolicitudReemplazoService;

class TramitePolicy
{
    public function __construct(
        private readonly AccesoOperativoService $accesos,
        private readonly AlcanceSolicitudReemplazoService $alcanceReemplazos,
    ) {}

    public function viewAny(User $user): bool
    {
        return $user->canAny(['tramites.ver_propios', 'tramites.ver_unidad', 'tramites.ver_todos']);
    }

    public function view(User $user, Tramite $tramite): bool
    {
        return $user->can('tramites.ver_todos')
            || ($user->can('tramites.ver_propios') && $tramite->created_by === $user->id)
            || ($tramite->tipoTramite?->codigo === 'REEMPLAZO' && $tramite->unidadOrganizacional !== null && ($this->alcanceReemplazos->tienePermisoYAlcance($user, 'reemplazos.crear', $tramite->unidadOrganizacional, today()) || $this->accesos->tienePermisoYAcceso($user, 'reemplazos.revisar', $tramite->unidadOrganizacional, today()) || $this->accesos->tienePermisoYAcceso($user, 'reemplazos.generar_documento', $tramite->unidadOrganizacional, today()) || $this->accesos->tienePermisoYAcceso($user, 'reemplazos.formalizar', $tramite->unidadOrganizacional, today())));
    }

    public function create(User $user): bool
    {
        return $user->can('tramites.crear');
    }
}
