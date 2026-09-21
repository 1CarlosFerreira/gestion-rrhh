<?php

namespace Tests\Feature;

use App\Enums\AlcanceAccesoOperativo;
use App\Models\CalidadContractual;
use App\Models\Estamento;
use App\Models\Persona;
use App\Models\TipoUnidadOrganizacional;
use App\Models\UnidadOrganizacional;
use App\Models\User;
use App\Models\UserUnidadAcceso;
use App\Services\Dotacion\DotacionService;
use Database\Seeders\RolesPermisosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DotacionContinuidadAccesoSistemaTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Persona $persona;

    private UnidadOrganizacional $unidad;

    private Estamento $estamento;

    private CalidadContractual $calidad;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesPermisosSeeder::class);
        $this->admin = User::factory()->create(['active' => true]);
        $this->admin->assignRole('Administrador');
        $this->persona = Persona::query()->create(['rut' => '11111111-1', 'nombres' => 'Persona', 'apellido_paterno' => 'Acceso', 'active' => true]);
        $tipo = TipoUnidadOrganizacional::query()->create(['codigo' => 'UNIDAD', 'nombre' => 'Unidad', 'activo' => true]);
        $this->unidad = UnidadOrganizacional::query()->create(['codigo' => 'U1', 'nombre' => 'Unidad Uno', 'tipo_unidad_organizacional_id' => $tipo->id, 'activo' => true]);
        $this->estamento = Estamento::query()->create(['codigo' => 'EST', 'nombre' => 'Estamento', 'activo' => true]);
        $this->calidad = CalidadContractual::query()->create(['codigo' => 'CAL', 'nombre' => 'Calidad', 'activo' => true, 'orden' => 1]);
    }

    public function test_alta_sin_user_ofrece_configurar_acceso_y_finalizar_no_crea_usuario(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.dotacion.store'), $this->datos());
        $vinculo = $this->persona->vinculosDotacion()->sole();

        $response->assertRedirect(route('admin.dotacion.continue', ['vinculo' => $vinculo]));
        $this->actingAs($this->admin)
            ->get($response->headers->get('Location'))
            ->assertOk()
            ->assertSee('✓ Incorporación completada')
            ->assertSee('Esta persona todavía no tiene una cuenta de usuario.')
            ->assertSee('Finalizar')
            ->assertSee('Configurar acceso al sistema →')
            ->assertSee(route('admin.usuarios.create-for-persona', $this->persona), false);

        $this->actingAs($this->admin)->get(route('admin.dotacion.index'))->assertOk();
        $this->assertNull($this->persona->fresh()->user);
        $this->assertDatabaseCount('user_unidad_accesos', 0);
    }

    public function test_configurar_acceso_reutiliza_el_formulario_existente_con_persona_determinada(): void
    {
        $vinculo = app(DotacionService::class)->crear($this->datos(), $this->admin);

        $this->actingAs($this->admin)
            ->get(route('admin.dotacion.continue', $vinculo))
            ->assertOk()
            ->assertSee(route('admin.usuarios.create-for-persona', $this->persona), false);

        $this->actingAs($this->admin)
            ->get(route('admin.usuarios.create-for-persona', $this->persona))
            ->assertOk()
            ->assertSee($this->persona->nombre_completo)
            ->assertSee($this->persona->rut);
    }

    public function test_persona_con_user_muestra_cuenta_roles_alcances_y_accesos_sin_duplicarlos(): void
    {
        $user = $this->usuarioPersona();
        $user->assignRole('Solicitante');
        UserUnidadAcceso::query()->create([
            'user_id' => $user->id,
            'unidad_organizacional_id' => $this->unidad->id,
            'alcance' => AlcanceAccesoOperativo::SOLO_UNIDAD,
            'vigente_desde' => today(),
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->post(route('admin.dotacion.store'), $this->datos([
            'responsabilidad_tipo' => 'TITULAR',
            'responsabilidad_desde' => today()->toDateString(),
        ]));

        $this->actingAs($this->admin)
            ->get($response->headers->get('Location'))
            ->assertOk()
            ->assertSee('✓ Usuario existente')
            ->assertSee($user->email)
            ->assertSee('Solicitante')
            ->assertSee('Alcance por responsabilidades vigentes')
            ->assertSee($this->unidad->nombre)
            ->assertSee('Titular')
            ->assertSee('Accesos operativos vigentes')
            ->assertSee('Cubre esta unidad')
            ->assertSee('Revisar acceso →')
            ->assertSee(route('admin.usuarios.perfil-acceso.edit', $user), false)
            ->assertDontSee('Configurar acceso al sistema →');

        $this->assertDatabaseCount('users', 2);
        $this->assertDatabaseCount('user_unidad_accesos', 1);
    }

    public function test_segunda_unidad_conserva_user_roles_y_accesos_existentes(): void
    {
        $user = $this->usuarioPersona();
        $user->assignRole(['Solicitante', 'Funcionario']);
        $otraUnidad = UnidadOrganizacional::query()->create([
            'codigo' => 'U2',
            'nombre' => 'Unidad Dos',
            'tipo_unidad_organizacional_id' => $this->unidad->tipo_unidad_organizacional_id,
            'activo' => true,
        ]);
        app(DotacionService::class)->crear($this->datos(), $this->admin);

        $response = $this->actingAs($this->admin)->post(route('admin.dotacion.store'), $this->datos([
            'unidad_organizacional_id' => $otraUnidad->id,
            'cargo_funcion' => 'Segundo cargo',
        ]));

        $this->actingAs($this->admin)
            ->get($response->headers->get('Location'))
            ->assertOk()
            ->assertSee($otraUnidad->nombre)
            ->assertSee('Solicitante, Funcionario');

        $this->assertSame($user->id, $this->persona->fresh()->user->id);
        $this->assertEqualsCanonicalizing(['Solicitante', 'Funcionario'], $user->fresh()->getRoleNames()->all());
        $this->assertDatabaseCount('users', 2);
        $this->assertDatabaseCount('user_unidad_accesos', 0);
    }

    public function test_solo_administrador_con_autorizacion_actual_puede_ver_la_continuidad(): void
    {
        $vinculo = app(DotacionService::class)->crear($this->datos(), $this->admin);
        $gestionPersonas = User::factory()->create(['active' => true]);
        $gestionPersonas->assignRole('Gestión de Personas');
        $gestionPersonas->givePermissionTo(['admin.usuarios', 'dotacion.gestionar']);

        $this->actingAs($gestionPersonas)
            ->get(route('admin.dotacion.continue', $vinculo))
            ->assertForbidden();
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

    private function datos(array $cambios = []): array
    {
        return [...[
            'persona_id' => $this->persona->id,
            'unidad_organizacional_id' => $this->unidad->id,
            'estamento_id' => $this->estamento->id,
            'profesion_id' => null,
            'calidad_contractual_id' => $this->calidad->id,
            'cargo_funcion' => 'Cargo base',
            'grado_eus' => null,
            'vigente_desde' => today()->toDateString(),
            'vigente_hasta' => null,
            'origen' => 'MANUAL',
            'observacion' => null,
            'responsabilidad_tipo' => 'FUNCIONARIO',
            'responsabilidad_desde' => null,
            'responsabilidad_hasta' => null,
        ], ...$cambios];
    }
}
