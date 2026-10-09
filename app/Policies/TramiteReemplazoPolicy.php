<?php

namespace App\Policies;

use App\Enums\ModalidadSolicitudContrato;
use App\Models\Tramite;
use App\Models\User;
use App\Services\Accesos\AccesoOperativoService;
use App\Services\Reemplazos\AlcanceSolicitudReemplazoService;
use App\Services\SolicitudesContrato\ContextoSolicitudContratoService;

class TramiteReemplazoPolicy
{
    public function __construct(
        private readonly AccesoOperativoService $accesos,
        private readonly AlcanceSolicitudReemplazoService $alcance,
        private readonly ContextoSolicitudContratoService $contexto,
    ) {}

    public function create(User $user): bool
    {
        return $user->active && $user->can('reemplazos.crear') && $this->contexto->unidadesAutorizadas($user, today())->isNotEmpty();
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
            && $this->puedeGestionar($user, $tramite);
    }

    public function generateDocument(User $user, Tramite $tramite): bool
    {
        return $user->active
            && $user->can('reemplazos.generar_documento')
            && $tramite->solicitudContrato === null
            && $this->puedeGestionar($user, $tramite);
    }

    public function formalize(User $user, Tramite $tramite): bool
    {
        return $user->active
            && $user->can('reemplazos.formalizar')
            && $this->puedeGestionar($user, $tramite);
    }

    private function puedeOperar(User $user, Tramite $tramite): bool
    {
        if ($tramite->solicitudContrato !== null) {
            if ($tramite->solicitudContrato->modalidad !== ModalidadSolicitudContrato::TRANSITORIA) {
                return false;
            }

            return $user->active && $user->can('reemplazos.crear')
                && $this->contexto->tieneAlcance($user, $tramite->solicitudContrato, today());
        }

        return $tramite->unidadOrganizacional !== null
            && $this->alcance->tienePermisoYAlcance($user, 'reemplazos.crear', $tramite->unidadOrganizacional, today());
    }

    private function puedeGestionar(User $user, Tramite $tramite): bool
    {
        if ($tramite->solicitudContrato !== null) {
            if ($tramite->solicitudContrato->modalidad !== ModalidadSolicitudContrato::TRANSITORIA) {
                return false;
            }

            return $user->can('reemplazos.alcance_global') || $this->contexto->tieneAlcance($user, $tramite->solicitudContrato, today());
        }

        return $tramite->unidadOrganizacional !== null
            && ($user->can('reemplazos.alcance_global') || $this->accesos->tieneAcceso($user, $tramite->unidadOrganizacional, today()));
    }
}
