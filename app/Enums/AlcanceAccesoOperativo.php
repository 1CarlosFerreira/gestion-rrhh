<?php

namespace App\Enums;

enum AlcanceAccesoOperativo: string
{
    case SOLO_UNIDAD = 'SOLO_UNIDAD';
    case UNIDAD_Y_DESCENDIENTES = 'UNIDAD_Y_DESCENDIENTES';

    public function etiqueta(): string
    {
        return match ($this) {
            self::SOLO_UNIDAD => 'Solo unidad',
            self::UNIDAD_Y_DESCENDIENTES => 'Unidad y descendientes',
        };
    }
}
