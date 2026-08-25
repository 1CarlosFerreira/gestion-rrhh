<?php

namespace Database\Seeders;

use App\Models\DocumentoPlantilla;
use App\Models\TipoDocumento;
use App\Models\TipoTramite;
use Illuminate\Database\Seeder;
use RuntimeException;

class DocumentoPlantillasSeeder extends Seeder
{
    public function run(): void
    {
        $relativePath = 'resources/templates/horas-extra/informe_tecnico_horas_extra_v1.xlsx';
        $absolutePath = base_path($relativePath);
        if (! is_file($absolutePath)) {
            throw new RuntimeException('No existe la plantilla institucional de Informe Técnico HE.');
        }

        DocumentoPlantilla::query()->updateOrCreate(
            ['codigo' => 'HE_INFORME_TECNICO', 'version' => 1],
            [
                'tipo_tramite_id' => TipoTramite::query()->where('codigo', 'HORAS_EXTRAORDINARIAS')->firstOrFail()->id,
                'tipo_documento_id' => TipoDocumento::query()->where('codigo', 'INFORME_TECNICO')->firstOrFail()->id,
                'nombre' => 'Informe Técnico de Horas Extraordinarias',
                'template_path' => $relativePath,
                'mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'sha256' => hash_file('sha256', $absolutePath),
                'active' => true,
            ],
        );
    }
}
