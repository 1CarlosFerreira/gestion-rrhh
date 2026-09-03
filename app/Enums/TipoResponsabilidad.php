<?php

namespace App\Enums;

enum TipoResponsabilidad: string
{
    case TITULAR = 'TITULAR';
    case SUBROGANTE = 'SUBROGANTE';

    public function etiqueta(): string
    {
        return match ($this) {
            self::TITULAR => 'Titular',
            self::SUBROGANTE => 'Subrogante',
        };
    }
}
