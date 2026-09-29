<?php

namespace App\Support\Tramites;

use App\Models\Tramite;

class PresentarTramiteBandeja
{
    public function make(Tramite $tramite, array $navigationContext = []): TramiteBandejaItem
    {
        [$principal, $secundario, $detalleUrl] = match ($tramite->tipoTramite?->codigo) {
            'REEMPLAZO' => [
                $tramite->reemplazo?->funcionario?->nombre_completo ?? 'Persona no disponible',
                $tramite->reemplazo?->reemplazante?->nombre_completo,
                route('reemplazos.show', ['tramite' => $tramite, ...$navigationContext]),
            ],
            default => ['Asunto no disponible', null, null],
        };

        return new TramiteBandejaItem(
            codigo: $tramite->codigo,
            tipo: $tramite->tipoTramite?->nombre ?? 'Tipo no disponible',
            personaAsunto: $principal,
            personaAsuntoSecundario: $secundario,
            unidad: $tramite->unidadOrganizacional?->nombre ?? 'Sin unidad',
            estado: $tramite->estadoTramite,
            creador: $tramite->creador?->name ?? 'Usuario no disponible',
            fecha: $tramite->created_at,
            detalleUrl: $detalleUrl,
        );
    }
}
