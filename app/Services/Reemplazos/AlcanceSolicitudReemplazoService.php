<?php

namespace App\Services\Reemplazos;

use App\Models\UnidadOrganizacional;
use App\Models\User;
use App\Services\Alcances\AlcanceFuncionalUnidadResolver;
use Illuminate\Support\Collection;

class AlcanceSolicitudReemplazoService
{
    public function __construct(private readonly AlcanceFuncionalUnidadResolver $alcance) {}

    public function unidadesAutorizadas(User $user, string|\DateTimeInterface $fecha): Collection
    {
        return $this->alcance->unidadesAutorizadas($user, $fecha);
    }

    public function puedeOperarEn(User $user, UnidadOrganizacional $unidad, string|\DateTimeInterface $fecha): bool
    {
        return $this->alcance->incluye($user, $unidad, $fecha);
    }

    public function tienePermisoYAlcance(User $user, string $permiso, UnidadOrganizacional $unidad, string|\DateTimeInterface $fecha): bool
    {
        return $user->can($permiso) && $this->puedeOperarEn($user, $unidad, $fecha);
    }
}
