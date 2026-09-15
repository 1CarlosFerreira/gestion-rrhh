<?php

namespace Tests\Feature;

use App\Models\ClasificacionArea;
use Database\Seeders\ClasificacionesAreaOficialesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClasificacionesAreaSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_official_area_classifications_are_seeded_idempotently_and_active(): void
    {
        $this->seed(ClasificacionesAreaOficialesSeeder::class);
        $this->seed(ClasificacionesAreaOficialesSeeder::class);

        $esperadas = [
            'AREA-CRITICA' => 'Áreas críticas',
            'AREA-SEMI-CRITICA' => 'Áreas Semi-Críticas',
            'AREA-APOYO-ASISTENCIAL' => 'Áreas de apoyo Asistencial',
            'AREA-APOYO-ADMINISTRATIVO' => 'Área de Apoyo Administrativo y no crítico',
        ];

        $this->assertSame(4, ClasificacionArea::query()->count());

        foreach ($esperadas as $codigo => $nombre) {
            $this->assertDatabaseHas('clasificaciones_area', [
                'codigo' => $codigo,
                'nombre' => $nombre,
                'activo' => true,
            ]);
        }
    }
}
