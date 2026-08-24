<?php

namespace Tests\Feature;

use App\Models\EstadoTramite;
use App\Models\TipoTramite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PhaseOneSecurityAndCatalogsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_guest_cannot_access_protected_areas(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
        $this->get('/admin/usuarios')->assertRedirect('/login');
    }

    public function test_valid_login_works(): void
    {
        $response = $this->post('/login', ['email' => 'admin@example.test', 'password' => 'password']);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticated();
    }

    public function test_public_registration_is_disabled_and_inactive_users_cannot_login(): void
    {
        $this->get('/register')->assertNotFound();

        $user = User::factory()->create(['active' => false, 'password' => 'password']);
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_administrator_can_access_administration(): void
    {
        $admin = User::query()->where('email', 'admin@example.test')->firstOrFail();

        $this->actingAs($admin)->get('/admin/usuarios')->assertOk();
        $this->actingAs($admin)->get('/admin/catalogos')->assertOk();
    }

    public function test_jefe_de_servicio_cannot_access_administration(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Jefe de Servicio');

        $this->actingAs($user)->get('/admin/usuarios')->assertForbidden();
        $this->actingAs($user)->get('/admin/catalogos')->assertForbidden();
    }

    public function test_gestion_de_personas_only_accesses_administration_with_explicit_permission(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Gestión de Personas');

        $this->actingAs($user)->get('/admin/catalogos')->assertForbidden();

        $user->givePermissionTo('admin.catalogos');
        $this->actingAs($user->fresh())->get('/admin/catalogos')->assertOk();
    }

    public function test_roles_and_permissions_are_seeded(): void
    {
        $this->assertSame(3, Role::query()->count());
        $this->assertSame(24, Permission::query()->count());
        $this->assertTrue(Role::findByName('Administrador')->hasPermissionTo('admin.usuarios'));
        $this->assertTrue(Role::findByName('Administrador')->hasPermissionTo('docdigital.registrar_formalizacion'));
    }

    public function test_process_types_and_their_states_are_seeded_correctly(): void
    {
        $reemplazo = TipoTramite::query()->where('codigo', 'REEMPLAZO')->firstOrFail();
        $horasExtra = TipoTramite::query()->where('codigo', 'HORAS_EXTRAORDINARIAS')->firstOrFail();

        $this->assertCount(8, $reemplazo->estados);
        $this->assertCount(10, $horasExtra->estados);
        $this->assertTrue($reemplazo->estados->contains('codigo', 'DEVUELTA_CORRECCION'));
        $this->assertTrue($horasExtra->estados->contains('codigo', 'EN_CORRECCION_SIRH'));
    }

    public function test_state_codes_are_unique_within_each_process_type(): void
    {
        EstadoTramite::query()->get()->groupBy('tipo_tramite_id')->each(function ($states): void {
            $this->assertSame($states->count(), $states->pluck('codigo')->unique()->count());
        });

        $this->assertSame(2, EstadoTramite::query()->where('codigo', 'BORRADOR')->count());
    }

    public function test_main_catalogs_are_seeded_without_fictitious_grades(): void
    {
        $this->assertDatabaseCount('unidades_servicios', 48);
        $this->assertDatabaseCount('estamentos', 8);
        $this->assertDatabaseCount('profesiones', 27);
        $this->assertDatabaseCount('tipos_tramite', 2);
        $this->assertDatabaseCount('estados_tramite', 18);
        $this->assertDatabaseCount('transiciones_estado', 18);
        $this->assertDatabaseCount('tipos_reemplazo', 5);
        $this->assertDatabaseCount('clasificaciones_area', 4);
        $this->assertDatabaseCount('grados_eus', 0);
    }

    public function test_phase_three_and_later_tables_do_not_exist(): void
    {
        foreach (['tramites', 'tramite_historial', 'tramite_adjuntos', 'tramite_reemplazos', 'tramite_horas_extra'] as $table) {
            $this->assertFalse(Schema::hasTable($table));
        }
    }
}
