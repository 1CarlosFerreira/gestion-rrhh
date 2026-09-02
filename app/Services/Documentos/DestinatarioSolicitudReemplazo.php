<?php

namespace App\Services\Documentos;

use App\Models\User;

class DestinatarioSolicitudReemplazo
{
    public const ROL_SUBDIRECTOR_PERSONAS = 'Subdirector/a de Gestión y Desarrollo de las Personas';

    public function para(User $creador): string
    {
        return $creador->hasRole(self::ROL_SUBDIRECTOR_PERSONAS)
            ? 'DIRECTOR DEL HOSPITAL'
            : 'SUBDIRECCIÓN DE GESTIÓN Y DESARROLLO DE LAS PERSONAS';
    }
}
