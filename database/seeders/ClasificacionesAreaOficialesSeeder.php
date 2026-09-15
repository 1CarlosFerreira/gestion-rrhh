<?php

namespace Database\Seeders;

use App\Models\ClasificacionArea;
use Illuminate\Database\Seeder;

class ClasificacionesAreaOficialesSeeder extends Seeder
{
    public function run(): void
    {
        $clasificaciones = [
            'AREA-CRITICA' => 'Áreas críticas',
            'AREA-SEMI-CRITICA' => 'Áreas Semi-Críticas',
            'AREA-APOYO-ASISTENCIAL' => 'Áreas de apoyo Asistencial',
            'AREA-APOYO-ADMINISTRATIVO' => 'Área de Apoyo Administrativo y no crítico',
        ];

        foreach ($clasificaciones as $codigo => $nombre) {
            ClasificacionArea::query()->updateOrCreate(
                ['codigo' => $codigo],
                ['nombre' => $nombre, 'activo' => true],
            );
        }
    }
}
