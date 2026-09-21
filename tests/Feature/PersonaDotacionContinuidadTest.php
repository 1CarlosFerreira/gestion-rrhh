<?php

namespace Tests\Feature;

use App\Enums\AlcanceAccesoOperativo;
use App\Models\CalidadContractual;
use App\Models\Persona;
use App\Models\TipoUnidadOrganizacional;
use App\Models\UnidadOrganizacional;
use App\Models\User;
use App\Models\UserUnidadAcceso;
use Database\Seeders\RolesPermisosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PersonaDotacionContinuidadTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private UnidadOrganizacional $unidadPermitida;

    private UnidadOrganizacional $unidadRestringida;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesPermisosSeeder::class);
        $this->user = User::factory()->create(['active' => true]);
        $this->user->assignRole('Administrador');

        CalidadContractual::query()->create([
            'codigo' => 'CONTRATA',
            'nombre' => 'Contrata',
            'activo' => true,
            'orden' => 1,
        ]);

        $tipo = TipoUnidadOrganizacional::query()->create([
            'codigo' => 'UNIDAD',
            'nombre' => 'Unidad',
            'activo' => true,
        ]);
        $this->unidadPermitida = $this->unidad($tipo->id, 'PERMITIDA', 'Unidad Permitida');
        $this->unidadRestringida = $this->unidad($tipo->id, 'RESTRINGIDA', 'Unidad Restringida');
    }

    public function test_guardar_persona_continua_redirigiendo_a_su_ficha(): void
    {
        $response = $this->actingAs($this->user)->post(route('admin.personas.store'), $this->datosPersona());

        $persona = Persona::query()->where('rut', '12345678-5')->sole();
        $response->assertRedirect(route('admin.personas.show', $persona));
    }

    public function test_guardar_y_agregar_redirige_al_alta_de_dotacion_con_la_persona(): void
    {
        $response = $this->actingAs($this->user)->post(route('admin.personas.store'), [
            ...$this->datosPersona(),
            'continuar' => 'dotacion',
        ]);

        $persona = Persona::query()->where('rut', '12345678-5')->sole();
        $response->assertRedirect(route('admin.dotacion.create', ['persona_id' => $persona->id]));
    }

    public function test_persona_llega_preseleccionada_y_admin_ve_unidades_activas_sin_accesos_artificiales(): void
    {
        $persona = Persona::query()->create($this->datosPersona());

        $this->actingAs($this->user)
            ->get(route('admin.dotacion.create', ['persona_id' => $persona->id]))
            ->assertOk()
            ->assertViewHas('personaSeleccionadaId', $persona->id)
            ->assertSee($this->unidadPermitida->nombre)
            ->assertSee($this->unidadRestringida->nombre);

        $this->assertCount(0, $this->user->accesosOperativos);
    }

    public function test_sin_permiso_de_dotacion_no_se_ofrece_ni_se_autoriza_la_continuacion(): void
    {
        $usuario = User::factory()->create(['active' => true]);
        $usuario->givePermissionTo(['personas.ver', 'personas.gestionar']);

        $this->actingAs($usuario)
            ->get(route('admin.personas.create'))
            ->assertOk()
            ->assertDontSee('Guardar y agregar a dotación');

        $response = $this->actingAs($usuario)->post(route('admin.personas.store'), [
            ...$this->datosPersona(),
            'continuar' => 'dotacion',
        ]);
        $persona = Persona::query()->where('rut', '12345678-5')->sole();
        $response->assertRedirect(route('admin.personas.show', $persona));
    }

    public function test_gestion_personas_no_puede_continuar_a_dotacion_aunque_se_le_asigne_el_permiso_directamente(): void
    {
        $gestionPersonas = User::factory()->create(['active' => true]);
        $gestionPersonas->assignRole('Gestión de Personas');
        $gestionPersonas->givePermissionTo('dotacion.gestionar');
        $this->darAcceso($gestionPersonas, $this->unidadPermitida);

        $this->actingAs($gestionPersonas)
            ->get(route('admin.personas.create'))
            ->assertOk()
            ->assertDontSee('Guardar y agregar a dotación');

        $response = $this->actingAs($gestionPersonas)->post(route('admin.personas.store'), [
            ...$this->datosPersona(),
            'continuar' => 'dotacion',
        ]);
        $persona = Persona::query()->where('rut', '12345678-5')->sole();
        $response->assertRedirect(route('admin.personas.show', $persona));
    }

    public function test_persona_inactiva_no_continua_a_dotacion(): void
    {
        $response = $this->actingAs($this->user)->post(route('admin.personas.store'), [
            ...$this->datosPersona(),
            'active' => false,
            'continuar' => 'dotacion',
        ]);

        $persona = Persona::query()->where('rut', '12345678-5')->sole();
        $response->assertRedirect(route('admin.personas.show', $persona));
        $this->assertFalse($persona->active);
    }

    public function test_ficha_mantiene_condiciones_y_muestra_nuevo_texto_de_accion(): void
    {
        $persona = Persona::query()->create($this->datosPersona());

        $this->actingAs($this->user)
            ->get(route('admin.personas.show', $persona))
            ->assertOk()
            ->assertSee('+ Agregar a dotación')
            ->assertDontSee('Agregar vínculo de dotación');
    }

    private function datosPersona(): array
    {
        return [
            'rut' => '12345678-5',
            'nombres' => 'Persona',
            'apellido_paterno' => 'Continuidad',
            'apellido_materno' => null,
            'active' => true,
        ];
    }

    private function unidad(int $tipoId, string $codigo, string $nombre): UnidadOrganizacional
    {
        return UnidadOrganizacional::query()->create([
            'codigo' => $codigo,
            'nombre' => $nombre,
            'tipo_unidad_organizacional_id' => $tipoId,
            'activo' => true,
        ]);
    }

    private function darAcceso(User $user, UnidadOrganizacional $unidad): void
    {
        UserUnidadAcceso::query()->create([
            'user_id' => $user->id,
            'unidad_organizacional_id' => $unidad->id,
            'alcance' => AlcanceAccesoOperativo::SOLO_UNIDAD,
            'vigente_desde' => today(),
            'created_by' => $user->id,
        ]);
    }
}
