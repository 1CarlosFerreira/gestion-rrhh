<?php

namespace App\Services\Reemplazos;

use App\Enums\TipoResponsabilidad;
use App\Models\UnidadOrganizacional;
use App\Models\User;
use App\Services\Accesos\AccesoOperativoService;
use Illuminate\Support\Collection;

class AlcanceSolicitudReemplazoService
{
    public function __construct(private readonly AccesoOperativoService $accesos) {}

    public function unidadesAutorizadas(User $user, string|\DateTimeInterface $fecha): Collection
    {
        if (! $user->active) {
            return collect();
        }

        $unidades = $this->accesos->unidadesAccesibles($user, $fecha)
            ->where('activo', true)
            ->keyBy('id');

        if ($user->persona_id === null) {
            return $unidades->values();
        }

        $responsabilidades = $user->persona->responsabilidades()
            ->with('unidad')
            ->vigentesEn($fecha)
            ->whereIn('tipo', [TipoResponsabilidad::TITULAR->value, TipoResponsabilidad::SUBROGANTE->value])
            ->where('puede_aprobar', true)
            ->get();

        foreach ($responsabilidades as $responsabilidad) {
            if ($responsabilidad->unidad->activo) {
                $unidades->put($responsabilidad->unidad->id, $responsabilidad->unidad);
            }
        }

        return $unidades->values();
    }

    public function puedeOperarEn(User $user, UnidadOrganizacional $unidad, string|\DateTimeInterface $fecha): bool
    {
        if (! $unidad->activo) {
            return false;
        }

        return $this->unidadesAutorizadas($user, $fecha)->contains('id', $unidad->id);
    }

    public function tienePermisoYAlcance(User $user, string $permiso, UnidadOrganizacional $unidad, string|\DateTimeInterface $fecha): bool
    {
        return $user->can($permiso) && $this->puedeOperarEn($user, $unidad, $fecha);
    }
}
