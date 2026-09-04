<?php

namespace Database\Seeders;

use App\Models\EstadoTramite;
use App\Models\TipoReemplazo;
use App\Models\TipoTramite;
use App\Models\TransicionEstado;
use Illuminate\Database\Seeder;

class ReemplazosV2Seeder extends Seeder
{
    public function run(): void
    {
        $tipo = TipoTramite::query()->updateOrCreate(['codigo' => 'REEMPLAZO'], ['nombre' => 'Solicitud de Reemplazo', 'activo' => true]);
        $estados = [];
        foreach (['BORRADOR' => ['Borrador', 1], 'ENVIADA_GESTION_PERSONAS' => ['Enviada a Gestión de Personas', 2], 'EN_REVISION' => ['En revisión', 3], 'DEVUELTA_PARA_CORRECCION' => ['Devuelta para corrección', 4], 'LISTA_GENERAR_DOCUMENTO' => ['Lista para generar documento', 5], 'DOCUMENTO_GENERADO' => ['Documento generado', 6], 'FORMALIZADA' => ['Formalizada', 7]] as $codigo => [$nombre, $orden]) {
            $estados[$codigo] = EstadoTramite::query()->updateOrCreate(['tipo_tramite_id' => $tipo->id, 'codigo' => $codigo], ['nombre' => $nombre, 'orden' => $orden, 'activo' => true]);
        }
        foreach ([['BORRADOR', 'ENVIADA_GESTION_PERSONAS', 'ENVIAR_A_GESTION_PERSONAS', 'reemplazos.crear', false], ['ENVIADA_GESTION_PERSONAS', 'EN_REVISION', 'INICIAR_REVISION', 'reemplazos.revisar', false], ['EN_REVISION', 'DEVUELTA_PARA_CORRECCION', 'DEVOLVER_PARA_CORRECCION', 'reemplazos.revisar', true], ['DEVUELTA_PARA_CORRECCION', 'ENVIADA_GESTION_PERSONAS', 'REENVIAR_A_GESTION_PERSONAS', 'reemplazos.crear', false], ['EN_REVISION', 'LISTA_GENERAR_DOCUMENTO', 'APROBAR_ANTECEDENTES', 'reemplazos.revisar', false], ['LISTA_GENERAR_DOCUMENTO', 'DOCUMENTO_GENERADO', 'GENERAR_DOCUMENTO', 'reemplazos.generar_documento', false], ['DOCUMENTO_GENERADO', 'FORMALIZADA', 'FORMALIZAR_REEMPLAZO', 'reemplazos.formalizar', false]] as [$origen, $destino, $accion, $permiso, $observacion]) {
            TransicionEstado::query()->updateOrCreate(['tipo_tramite_id' => $tipo->id, 'estado_origen_id' => $estados[$origen]->id, 'codigo_accion' => $accion], ['estado_destino_id' => $estados[$destino]->id, 'permiso_requerido' => $permiso, 'requiere_observacion' => $observacion, 'activo' => true]);
        }
        $tipos = [
            'LICENCIA_MEDICA' => 'Licencia Médica',
            'PERMISO' => 'Permiso',
            'LICENCIA_MATERNAL' => 'Licencia Maternal',
            'CARGO_VACANTE' => 'Cargo Vacante',
        ];
        foreach ($tipos as $codigo => $nombre) {
            TipoReemplazo::query()->updateOrCreate(['codigo' => $codigo], ['nombre' => $nombre, 'activo' => true, 'orden' => array_search($codigo, array_keys($tipos), true) + 1]);
        }
    }
}
