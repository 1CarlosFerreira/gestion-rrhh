<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardAdministrativoTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_administrativo_solo_muestra_bienvenida_resumen_y_administracion_rapida(): void
    {
        $this->seed();
        $administrador = User::query()->where('email', 'admin@example.test')->firstOrFail();

        $this->actingAs($administrador)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSeeInOrder([
                'Panel general de administración y configuración del sistema.',
                'Resumen',
                'Administración rápida',
            ])
            ->assertDontSee('Mis trámites en curso')
            ->assertDontSee('Situaciones objetivas que conviene revisar.');
    }
}
