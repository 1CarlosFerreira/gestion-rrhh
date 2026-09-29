<?php

namespace App\Support\Tramites;

use App\Models\EstadoTramite;
use Carbon\CarbonInterface;

final readonly class TramiteBandejaItem
{
    public function __construct(
        public string $codigo,
        public string $tipo,
        public string $personaAsunto,
        public ?string $personaAsuntoSecundario,
        public string $unidad,
        public ?EstadoTramite $estado,
        public string $creador,
        public CarbonInterface $fecha,
        public ?string $detalleUrl,
    ) {}
}
