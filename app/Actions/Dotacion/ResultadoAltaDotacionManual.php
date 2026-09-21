<?php

namespace App\Actions\Dotacion;

use App\Models\PersonaUnidadVinculo;
use App\Models\UnidadResponsable;

readonly class ResultadoAltaDotacionManual
{
    public function __construct(
        public PersonaUnidadVinculo $vinculo,
        public ?UnidadResponsable $responsabilidad,
    ) {}
}
