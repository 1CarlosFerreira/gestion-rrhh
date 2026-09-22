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
        return $user->active && $user->canAny([
            'tramites.ver_unidades',
            'tramites.ver_todos',
            'reemplazos.crear',
            'reemplazos.revisar',
            'reemplazos.generar_documento',
            'reemplazos.formalizar',
        ]);
    }

    public function view(User $user, Tramite $tramite): bool
    {
        if (! $user->active) {
            return false;
        }

        if ($user->can('tramites.ver_todos')) {
            return true;
        }

        if ($tramite->tipoTramite?->codigo !== 'REEMPLAZO' || $tramite->unidadOrganizacional === null) {
            return false;
        }

        return ($user->can('tramites.ver_unidades') && $this->alcanceReemplazos->puedeOperarEn($user, $tramite->unidadOrganizacional, today()))
            || $this->alcanceReemplazos->tienePermisoYAlcance($user, 'reemplazos.crear', $tramite->unidadOrganizacional, today())
            || $this->accesos->tienePermisoYAcceso($user, 'reemplazos.revisar', $tramite->unidadOrganizacional, today())
            || $this->accesos->tienePermisoYAcceso($user, 'reemplazos.generar_documento', $tramite->unidadOrganizacional, today())
            || $this->accesos->tienePermisoYAcceso($user, 'reemplazos.formalizar', $tramite->unidadOrganizacional, today());
    }

    public function create(User $user): bool
    {
        return $user->active && $user->can('tramites.crear');
    }
}
