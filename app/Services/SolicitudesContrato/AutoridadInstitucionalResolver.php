<?php

namespace App\Services\SolicitudesContrato;

use App\Enums\ContextoAutoridadInstitucional;
use App\Enums\TipoResponsabilidad;
use App\Models\UnidadOrganizacional;
use App\Models\UnidadResponsable;
use Illuminate\Validation\ValidationException;

class AutoridadInstitucionalResolver
{
    public function resolver(
        UnidadOrganizacional $unidad,
        string|\DateTimeInterface $fecha,
        ContextoAutoridadInstitucional $contexto,
    ): UnidadResponsable {
        $tiposAdmitidos = match ($contexto) {
            ContextoAutoridadInstitucional::REGISTRO_SOLICITUD_CONTRATO => [
                TipoResponsabilidad::TITULAR->value,
                TipoResponsabilidad::SUBROGANTE->value,
            ],
        };

        $candidatas = UnidadResponsable::query()
            ->with('persona')
            ->where('unidad_organizacional_id', $unidad->id)
            ->whereIn('tipo', $tiposAdmitidos)
            ->vigentesEn($fecha)
            ->lockForUpdate()
            ->get();

        if ($candidatas->count() !== 1) {
            throw ValidationException::withMessages([
                'autoridad_institucional' => $candidatas->isEmpty()
                    ? 'No existe una autoridad institucional vigente e inequívoca para la unidad solicitante.'
                    : 'Existe más de una autoridad institucional vigente para el acto y no hay una regla confirmada que permita priorizarla.',
            ]);
        }

        return $candidatas->sole();
    }
}
