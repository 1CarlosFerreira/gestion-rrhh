<?php

namespace App\Enums;

enum RolDotacion: string
{
    case REEMPLAZO = 'REEMPLAZO';
    case SUBROGANTE = 'SUBROGANTE';
    case JEFATURA_TITULAR = 'JEFATURA_TITULAR';
    case INTEGRANTE = 'INTEGRANTE';

    public function etiqueta(): string
    {
        return match ($this) {
            self::REEMPLAZO => 'REEMPLAZO',
            self::SUBROGANTE => 'SUBROGANTE',
            self::JEFATURA_TITULAR => 'JEFATURA TITULAR',
            self::INTEGRANTE => 'INTEGRANTE',
        };
    }
}
