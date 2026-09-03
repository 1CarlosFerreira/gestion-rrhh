<?php

namespace App\Enums;

enum EstadoVinculoDotacion: string
{
    case FUTURO = 'FUTURO';
    case VIGENTE = 'VIGENTE';
    case FINALIZADO = 'FINALIZADO';
}
