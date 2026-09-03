<?php

namespace Database\Seeders;

use App\Models\TipoDocumento;
use Illuminate\Database\Seeder;

class TiposDocumentoSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['OTRO' => 'Otro', 'RESPALDO' => 'Respaldo', 'DOCUMENTO_GENERADO' => 'Documento generado'] as $codigo => $nombre) {
            TipoDocumento::query()->updateOrCreate(
                ['codigo' => $codigo],
                ['nombre' => $nombre, 'descripcion' => null, 'active' => true],
            );
        }
    }
}
