<?php

namespace App\Policies;

use App\Models\Tramite;
use App\Models\User;
use App\Services\Accesos\AccesoOperativoService;
use App\Services\Reemplazos\AlcanceSolicitudReemplazoService;

class TramiteReemplazoPolicy
{
    public function __construct(
        private readonly AccesoOperativoService $accesos,
        private readonly AlcanceSolicitudReemplazoService $alcance,
    ) {}

    public function create(User $user): bool
    {
        return $user->active && $user->can('reemplazos.crear') && $this->alcance->unidadesAutorizadas($user, today())->isNotEmpty();
    }

    public function view(User $user, Tramite $tramite): bool
    {
        return $this->puedeOperar($user, $tramite);
    }

    public function update(User $user, Tramite $tramite): bool
    {
        return in_array($tramite->estadoTramite?->codigo, ['BORRADOR', 'DEVUELTA_PARA_CORRECCION'], true) && $this->puedeOperar($user, $tramite);
    }

    public function review(User $user, Tramite $tramite): bool
    {
        return $user->active
            && $user->can('reemplazos.revisar')
            && $tramite->unidadOrganizacional !== null
            && ($user->can('tramites.ver_todos') || $this->accesos->tieneAcceso($user, $tramite->unidadOrganizacional, today()));
    }

    public function generateDocument(User $user, Tramite $tramite): bool
    {
        return $user->active
            && $user->can('reemplazos.generar_documento')
            && $tramite->unidadOrganizacional !== null
            && ($user->can('tramites.ver_todos') || $this->accesos->tieneAcceso($user, $tramite->unidadOrganizacional, today()));
    }

    public function formalize(User $user, Tramite $tramite): bool
    {
        return $user->active
            && $user->can('reemplazos.formalizar')
            && $tramite->unidadOrganizacional !== null
            && ($user->can('tramites.ver_todos') || $this->accesos->tieneAcceso($user, $tramite->unidadOrganizacional, today()));
    }

    private function puedeOperar(User $user, Tramite $tramite): bool
    {
        return $tramite->unidadOrganizacional !== null
            && $this->alcance->tienePermisoYAlcance($user, 'reemplazos.crear', $tramite->unidadOrganizacional, today());
    }
}
