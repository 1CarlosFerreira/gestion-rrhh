<?php

namespace Tests\Feature;

use App\Enums\AlcanceAccesoOperativo;
use App\Enums\TipoResponsabilidad;
use App\Models\Persona;
use App\Models\TipoUnidadOrganizacional;
use App\Models\UnidadOrganizacional;
use App\Models\UnidadResponsable;
use App\Models\User;
use Database\Seeders\RolesPermisosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserPerfilAccesoContinuidadTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Persona $persona;

    private UnidadOrganizacional $unidad;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesPermisosSeeder::class);
        $this->admin = User::factory()->create(['active' => true]);
        $this->admin->assignRole('Administrador');
        $this->persona = Persona::query()->create([
            'rut' => '12345678-9',
            'nombres' => 'Persona',
            'apellido_paterno' => 'Continuidad',
            'active' => true,
        ]);
        $tipo = TipoUnidadOrganizacional::query()->create(['codigo' => 'UNIDAD', 'nombre' => 'Unidad', 'activo' => true]);
        $this->unidad = UnidadOrganizacional::query()->create([
            'codigo' => 'U1',
            'nombre' => 'Subdirección de Gestión Administrativa',
            'tipo_unidad_organizacional_id' => $tipo->id,
            'activo' => true,
        ]);
    }

    public function test_user_creado_desde_continuidad_llega_al_perfil_sin_roles_ni_accesos_automaticos(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.usuarios.store-for-persona', [
            'persona' => $this->persona,
            'continuar_perfil' => 1,
        ]), $this->credenciales());

        $user = $this->persona->fresh()->user;
        $response->assertRedirect(route('admin.usuarios.perfil-acceso.edit', ['user' => $user, 'creado' => 1]));
        $this->assertCount(0, $user->roles);
        $this->assertDatabaseCount('user_unidad_accesos', 0);

        $this->actingAs($this->admin)
            ->get($response->headers->get('Location'))
            ->assertOk()
            ->assertSee('✓ Usuario creado correctamente')
            ->assertSee($this->persona->nombre_completo)
            ->assertSee('Administración completa del sistema.')
            ->assertSee('Consulta básica dentro de su ámbito de operación.')
            ->assertSee('El rol define las acciones disponibles; el alcance determina las unidades sobre las que puede operar.')
            ->assertSeeInOrder(['Rol del sistema', 'Alcance actual', 'Autorizaciones adicionales', 'Guardar roles y finalizar'])
            ->assertSee('Guardar roles y finalizar')
            ->assertSee('Agregar autorización adicional')
            ->assertDontSee('value="Administrador" checked', false)
            ->assertDontSee('value="Gestión de Personas" checked', false)
            ->assertDontSee('value="Jefatura" checked', false)
            ->assertDontSee('value="Solicitante" checked', false)
            ->assertDontSee('value="Funcionario" checked', false)
            ->assertSeeInOrder([
                'value="Administrador"',
                'value="Funcionario"',
                'value="Gestión de Personas"',
                'value="Jefatura"',
                'value="Solicitante"',
            ], false);
    }

    public function test_perfil_muestra_alcance_aportado_por_responsabilidad_y_no_crea_acceso(): void
    {
        $user = $this->usuarioPersona();
        UnidadResponsable::query()->create([
            'unidad_organizacional_id' => $this->unidad->id,
            'persona_id' => $this->persona->id,
            'tipo' => TipoResponsabilidad::TITULAR,
            'vigente_desde' => today()->subDay(),
            'puede_aprobar' => true,
            'created_by' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.usuarios.perfil-acceso.edit', $user))
            ->assertOk()
            ->assertSee('Alcance actual')
            ->assertSee('Titular')
            ->assertSee($this->unidad->nombre)
            ->assertSee('Aportado por responsabilidad institucional.')
            ->assertSee('Sin autorizaciones adicionales vigentes.');

        $this->assertDatabaseCount('user_unidad_accesos', 0);
    }

    public function test_puede_guardar_jefatura_y_solicitante_sin_modificar_otras_dimensiones(): void
    {
        $user = $this->usuarioPersona();

        $this->actingAs($this->admin)->put(route('admin.usuarios.roles.update', $user), [
            'roles' => ['Jefatura'],
        ])->assertRedirect();
        $this->assertSame(['Jefatura'], $user->fresh()->getRoleNames()->all());

        $response = $this->actingAs($this->admin)->put(route('admin.usuarios.roles.update', $user), [
            'roles' => ['Jefatura', 'Solicitante'],
            'finalizar_perfil' => 1,
        ]);

        $response->assertRedirect(route('admin.personas.show', $this->persona));
        $this->assertEqualsCanonicalizing(['Jefatura', 'Solicitante'], $user->fresh()->getRoleNames()->all());
        $this->assertDatabaseCount('users', 2);
        $this->assertDatabaseCount('unidad_responsables', 0);
        $this->assertDatabaseCount('user_unidad_accesos', 0);
        $this->assertDatabaseCount('persona_unidad_vinculos', 0);
    }

    public function test_user_existente_puede_revisar_el_mismo_perfil_y_acceso_operativo_llega_preseleccionado(): void
    {
        $user = $this->usuarioPersona();
        $user->assignRole('Funcionario');

        $this->actingAs($this->admin)
            ->get(route('admin.usuarios.perfil-acceso.edit', $user))
            ->assertOk()
            ->assertSee('Configurar perfil de acceso')
            ->assertSee('value="Jefatura"', false)
            ->assertSee('value="Funcionario" checked', false);

        $this->actingAs($this->admin)
            ->get(route('admin.accesos.create', ['user_id' => $user->id]))
            ->assertOk()
            ->assertSee('value="'.$user->id.'" selected', false)
            ->assertSee('name="continuar_perfil_user_id" value="'.$user->id.'"', false);
    }

    public function test_acceso_operativo_configurado_desde_el_perfil_reutiliza_el_modulo_y_regresa_al_perfil(): void
    {
        $user = $this->usuarioPersona();

        $response = $this->actingAs($this->admin)->post(route('admin.accesos.store'), [
            'user_id' => $user->id,
            'unidad_organizacional_id' => $this->unidad->id,
            'alcance' => AlcanceAccesoOperativo::SOLO_UNIDAD->value,
            'vigente_desde' => today()->toDateString(),
            'vigente_hasta' => null,
            'observacion' => null,
            'continuar_perfil_user_id' => $user->id,
        ]);

        $response->assertRedirect(route('admin.usuarios.perfil-acceso.edit', $user));
        $this->assertDatabaseHas('user_unidad_accesos', [
            'user_id' => $user->id,
            'unidad_organizacional_id' => $this->unidad->id,
            'alcance' => AlcanceAccesoOperativo::SOLO_UNIDAD->value,
        ]);
    }

    public function test_perfil_de_continuidad_es_solo_para_administrador_con_admin_usuarios(): void
    {
        $user = $this->usuarioPersona();
        $gestionPersonas = User::factory()->create(['active' => true]);
        $gestionPersonas->assignRole('Gestión de Personas');
        $gestionPersonas->givePermissionTo('admin.usuarios');

        $this->actingAs($gestionPersonas)
            ->get(route('admin.usuarios.perfil-acceso.edit', $user))
            ->assertForbidden();

        $this->actingAs($gestionPersonas)
            ->put(route('admin.usuarios.roles.update', $user), [
                'roles' => ['Solicitante'],
                'finalizar_perfil' => 1,
            ])
            ->assertForbidden();

        $this->assertFalse($user->fresh()->hasAnyRole());
    }

    private function usuarioPersona(): User
    {
        return User::factory()->create([
            'persona_id' => $this->persona->id,
            'name' => $this->persona->nombre_completo,
            'rut' => $this->persona->rut,
            'email' => 'persona@example.test',
            'active' => true,
        ]);
    }

    private function credenciales(): array
    {
        return [
            'email' => 'persona@example.test',
            'password' => 'password-segura',
            'password_confirmation' => 'password-segura',
        ];
    }
}
