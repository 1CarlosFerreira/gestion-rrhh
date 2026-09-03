<?php

namespace App\Enums;

enum OrigenVinculoDotacion: string
{
    case MANUAL = 'MANUAL';
    case IMPORTACION = 'IMPORTACION';
    case DOCUMENTO_FIRMADO = 'DOCUMENTO_FIRMADO';

    public function etiqueta(): string
    {
        return match ($this) {
            self::MANUAL => 'Manual',
            self::IMPORTACION => 'Importación',
            self::DOCUMENTO_FIRMADO => 'Documento firmado',
        };
    }
}
