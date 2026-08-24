<?php

namespace Database\Seeders;

use App\Models\TipoDocumento;
use Illuminate\Database\Seeder;

class TiposDocumentoSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['OTRO' => 'Otro', 'RESPALDO' => 'Respaldo', 'PLANILLA_SIRH' => 'Planilla SIRH', 'INFORME_TECNICO' => 'Informe Técnico', 'DOCUMENTO_GENERADO' => 'Documento Generado', 'DOCUMENTO_FINAL_DOCDIGITAL' => 'Documento Final DocDigital'] as $codigo => $nombre) {
            TipoDocumento::query()->updateOrCreate(['codigo' => $codigo], ['nombre' => $nombre, 'descripcion' => null, 'active' => true]);
        }
    }
}
