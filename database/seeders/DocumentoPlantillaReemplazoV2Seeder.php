<?php

namespace Database\Seeders;

use App\Models\DocumentoPlantilla;
use App\Models\TipoDocumento;
use App\Models\TipoTramite;
use Illuminate\Database\Seeder;

class DocumentoPlantillaReemplazoV2Seeder extends Seeder
{
    public function run(): void
    {
        $path = resource_path('views/pdf/reemplazos/solicitud.blade.php');
        DocumentoPlantilla::query()->updateOrCreate(
            ['codigo' => 'REEMPLAZO_SOLICITUD_PDF', 'version' => 1],
            ['tipo_tramite_id' => TipoTramite::query()->where('codigo', 'REEMPLAZO')->firstOrFail()->id, 'tipo_documento_id' => TipoDocumento::query()->where('codigo', 'DOCUMENTO_GENERADO')->firstOrFail()->id, 'nombre' => 'Solicitud de Reemplazo de Personal', 'template_path' => 'pdf.reemplazos.solicitud', 'mime_type' => 'application/pdf', 'sha256' => hash_file('sha256', $path), 'active' => true],
        );
    }
}
