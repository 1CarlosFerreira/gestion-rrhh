<?php

namespace Tests\Feature;

use App\Models\Persona;
use App\Models\UnidadOrganizacional;
use App\Models\Estamento;
use App\Models\CalidadContractual;
use App\Models\Profesion;
use App\Models\PersonaUnidadVinculo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class V2FAPersonasDotacionTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        // Crear rol Administrador si no existe
        $roleClass = \Spatie\Permission\Models\Role::class;
        if (!$roleClass::where('name', 'Administrador')->exists()) {
            $roleClass::create(['name' => 'Administrador']);
        }

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Administrador');
    }

    public function test_admin_can_list_personas()
    {
        $persona = Persona::factory()->create(['active' => true]);
        $response = $this->actingAs($this->admin)->get(route('admin.personas.index'));
        $response->assertStatus(200);
        $response->assertSee($persona->nombre_completo);
    }

    public function test_admin_can_search_personas_by_name()
    {
        $persona = Persona::factory()->create(['nombres' => 'Juan']);
        $response = $this->actingAs($this->admin)->get(route('admin.personas.index', ['buscar' => 'Juan']));
        $response->assertStatus(200);
        $response->assertSee('Juan');
    }

    public function test_admin_can_search_personas_by_rut()
    {
        $persona = Persona::factory()->create(['rut' => '12345678-9']);
        $response = $this->actingAs($this->admin)->get(route('admin.personas.index', ['buscar' => '12345678-9']));
        $response->assertStatus(200);
        $response->assertSee('12345678-9');
    }

    public function test_admin_can_create_persona()
    {
        $data = [
            'rut' => '98765432-1',
            'nombres' => 'Maria',
            'apellido_paterno' => 'Lopez',
            'apellido_materno' => 'Gomez',
            'active' => true,
        ];
        $response = $this->actingAs($this->admin)->post(route('admin.personas.store'), $data);
        $response->assertRedirect();
        $this->assertDatabaseHas('personas', ['rut' => '98765432-1']);
    }

    public function test_rut_must_be_unique()
    {
        $persona = Persona::factory()->create(['rut' => '11111111-1']);
        $data = [
            'rut' => '11111111-1',
            'nombres' => 'Test',
            'apellido_paterno' => 'Test',
            'active' => true,
        ];
        $response = $this->actingAs($this->admin)->post(route('admin.personas.store'), $data);
        $response->assertSessionHasErrors('rut');
    }

    public function test_admin_can_edit_persona()
    {
        $persona = Persona::factory()->create();
        $data = [
            'rut' => $persona->rut,
            'nombres' => 'Updated',
            'apellido_paterno' => $persona->apellido_paterno,
            'apellido_materno' => $persona->apellido_materno,
            'active' => $persona->active,
        ];
        $response = $this->actingAs($this->admin)->put(route('admin.personas.update', $persona), $data);
        $response->assertRedirect();
        $this->assertDatabaseHas('personas', ['nombres' => 'Updated']);
    }

    public function test_admin_can_view_persona_ficha()
    {
        $persona = Persona::factory()->create();
        $response = $this->actingAs($this->admin)->get(route('admin.personas.show', $persona));
        $response->assertStatus(200);
        $response->assertSee($persona->nombre_completo);
    }

    public function test_admin_can_create_vinculo_manual()
    {
        $persona = Persona::factory()->create();
        $unidad = UnidadOrganizacional::factory()->create();
        $estamento = Estamento::factory()->create(['activo' => true]);
        $calidad = CalidadContractual::factory()->create(['activo' => true]);
        $profesion = Profesion::factory()->create(['activo' => true]);

        $data = [
            'persona_id' => $persona->id,
            'unidad_organizacional_id' => $unidad->id,
            'estamento_id' => $estamento->id,
            'profesion_id' => $profesion->id,
            'calidad_contractual_id' => $calidad->id,
            'cargo_funcion' => 'Jefe de Servicio',
            'grado_eus' => 1,
            'vigente_desde' => now()->subMonth()->toDateString(),
            'vigente_hasta' => null,
            'observacion' => 'Test vinculo',
            'origen_tramite_id' => null,
        ];

        $response = $this->actingAs($this->admin)->post(route('admin.dotacion.store'), $data);
        $response->assertRedirect();
        $this->assertDatabaseHas('persona_unidad_vinculos', ['cargo_funcion' => 'Jefe de Servicio', 'origen_tramite_id' => null]);
    }

    public function test_vigente_hasta_cannot_be_before_vigente_desde()
    {
        $persona = Persona::factory()->create();
        $unidad = UnidadOrganizacional::factory()->create();
        $estamento = Estamento::factory()->create(['activo' => true]);
        $calidad = CalidadContractual::factory()->create(['activo' => true]);

        $data = [
            'persona_id' => $persona->id,
            'unidad_organizacional_id' => $unidad->id,
            'estamento_id' => $estamento->id,
            'calidad_contractual_id' => $calidad->id,
            'cargo_funcion' => 'Secretaria',
            'grado_eus' => null,
            'vigente_desde' => now()->toDateString(),
            'vigente_hasta' => now()->subDay()->toDateString(),
            'observacion' => null,
            'origen_tramite_id' => null,
        ];

        $response = $this->actingAs($this->admin)->post(route('admin.dotacion.store'), $data);
        $response->assertSessionHasErrors('vigente_hasta');
    }

    public function test_admin_can_close_vinculo()
    {
        $persona = Persona::factory()->create();
        $unidad = UnidadOrganizacional::factory()->create();
        $estamento = Estamento::factory()->create(['activo' => true]);
        $calidad = CalidadContractual::factory()->create(['activo' => true]);

        $vinculo = PersonaUnidadVinculo::factory()->create([
            'persona_id' => $persona->id,
            'unidad_organizacional_id' => $unidad->id,
            'estamento_id' => $estamento->id,
            'calidad_contractual_id' => $calidad->id,
            'cargo_funcion' => 'Secretaria',
            'vigente_desde' => now()->subMonth()->toDateString(),
            'vigente_hasta' => null,
            'origen_tramite_id' => null,
        ]);

        $response = $this->actingAs($this->admin)->patch(route('admin.dotacion.close', $vinculo), [
            'vigente_hasta' => now()->toDateString(),
        ]);
        $response->assertRedirect();
        $this->assertDatabaseHas('persona_unidad_vinculos', ['id' => $vinculo->id, 'vigente_hasta' => now()->toDateString()]);
    }

    public function test_non_authorized_user_cannot_manage_personas()
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)->get(route('admin.personas.index'));
        $response->assertStatus(403);
    }
}