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
    ): array {
        $tiposAdmitidos = match ($contexto) {
            ContextoAutoridadInstitucional::REGISTRO_SOLICITUD_CONTRATO => [
                TipoResponsabilidad::TITULAR->value,
                TipoResponsabilidad::SUBROGANTE->value,
            ],
        };

        $candidatas = UnidadResponsable::query()
            ->where('unidad_organizacional_id', $unidad->id)
            ->whereIn('tipo', $tiposAdmitidos)
            ->vigentesEn($fecha)
            ->lockForUpdate()
            ->get();

        $personas = $candidatas->pluck('persona_id')->unique();
        if ($personas->count() !== 1) {
            throw ValidationException::withMessages([
                'autoridad_institucional' => $candidatas->isEmpty()
                    ? 'No existe una autoridad institucional vigente para la unidad solicitante.'
                    : 'Las responsabilidades vigentes representan a personas distintas y no hay una regla confirmada para elegir la autoridad de este acto.',
            ]);
        }

        return [
            'persona_id' => $personas->first(),
            'responsabilidad_id' => $candidatas->count() === 1 ? $candidatas->first()->id : null,
            'responsabilidad_ids' => $candidatas->pluck('id')->all(),
        ];
    }
}
