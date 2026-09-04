<?php

namespace Tests\Feature;

use App\Models\CalidadContractual;
use Database\Seeders\CalidadesContractualesOficialesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalidadesContractualesOficialesTest extends TestCase
{
    use RefreshDatabase;

    private const OFICIALES = [
        'COMPRA_SERVICIO' => 'Compra De Servicio',
        'COMISION_SERVICIO' => 'Comision De Servicio',
        'CONTRATA' => 'Contrata',
        'HONORARIO' => 'Honorario',
        'REEMPLAZO' => 'Reemplazo',
        'TITULAR' => 'Titular',
        'ALUMNO' => 'Alumno',
        'TRABAJADOR_EXTERNO' => 'Trabajador Externo',
    ];

    public function test_seeder_creates_exactly_the_eight_official_active_values_identified_by_code(): void
    {
        $this->seed(CalidadesContractualesOficialesSeeder::class);

        $this->assertDatabaseCount('calidades_contractuales', 8);
        $this->assertSame(array_keys(self::OFICIALES), CalidadContractual::query()->orderBy('orden')->pluck('codigo')->all());
        foreach (self::OFICIALES as $codigo => $nombre) {
            $this->assertDatabaseHas('calidades_contractuales', ['codigo' => $codigo, 'nombre' => $nombre, 'activo' => true]);
        }
    }

    public function test_seeder_is_idempotent_and_preserves_existing_ids(): void
    {
        $this->seed(CalidadesContractualesOficialesSeeder::class);
        $ids = CalidadContractual::query()->pluck('id', 'codigo')->all();

        $this->seed(CalidadesContractualesOficialesSeeder::class);
        $this->seed(CalidadesContractualesOficialesSeeder::class);

        $this->assertDatabaseCount('calidades_contractuales', 8);
        $this->assertSame($ids, CalidadContractual::query()->pluck('id', 'codigo')->all());
    }

    public function test_seeder_reconciles_minor_capitalization_differences_without_parallel_record(): void
    {
        $existente = CalidadContractual::query()->create([
            'codigo' => 'trabajador_externo',
            'nombre' => 'trabajador externo',
            'activo' => false,
            'orden' => 99,
        ]);

        $this->seed(CalidadesContractualesOficialesSeeder::class);

        $this->assertDatabaseCount('calidades_contractuales', 8);
        $actualizada = CalidadContractual::query()->where('codigo', 'TRABAJADOR_EXTERNO')->sole();
        $this->assertSame($existente->id, $actualizada->id);
        $this->assertSame('Trabajador Externo', $actualizada->nombre);
        $this->assertTrue($actualizada->activo);
    }
}
