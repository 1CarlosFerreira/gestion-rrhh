<?php

namespace Database\Seeders;

use App\Models\EstadoTramite;
use App\Models\TipoReemplazo;
use App\Models\TipoTramite;
use Illuminate\Database\Seeder;

class ReemplazosV2Seeder extends Seeder
{
    public function run(): void
    {
        $tipo = TipoTramite::query()->updateOrCreate(['codigo' => 'REEMPLAZO'], ['nombre' => 'Solicitud de Reemplazo', 'activo' => true]);
        EstadoTramite::query()->updateOrCreate(['tipo_tramite_id' => $tipo->id, 'codigo' => 'BORRADOR'], ['nombre' => 'Borrador', 'orden' => 1, 'activo' => true]);
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
