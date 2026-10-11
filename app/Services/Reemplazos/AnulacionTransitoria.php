<?php

namespace App\Services\Reemplazos;

use App\Models\Tramite;

class AnulacionTransitoria
{
    public const ESTADOS = ['BORRADOR', 'ENVIADA_GESTION_PERSONAS', 'EN_REVISION', 'DEVUELTA_PARA_CORRECCION', 'LISTA_GENERAR_DOCUMENTO'];

    public function fueEnviada(Tramite $tramite): bool
    {
        return $tramite->historial()->whereIn('action_code', ['ENVIAR_A_GESTION_PERSONAS', 'REENVIAR_A_GESTION_PERSONAS'])->exists();
    }

    public function antesDelDocumento(Tramite $tramite): bool
    {
        return in_array($tramite->estadoTramite?->codigo, self::ESTADOS, true)
            && ! $tramite->documentosGenerados()->exists()
            && ! $tramite->formalizacionReemplazo()->exists();
    }
}
