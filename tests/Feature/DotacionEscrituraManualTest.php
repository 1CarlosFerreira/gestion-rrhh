<?php

namespace Tests\Feature;

use App\Enums\AlcanceAccesoOperativo;
use App\Enums\OrigenVinculoDotacion;
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

class DotacionEscrituraManualTest extends TestCase
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
        $this->persona = Persona::query()->create([
            'rut' => '11111111-1',
            'nombres' => 'Persona',
            'apellido_paterno' => 'Dotación',
            'active' => true,
        ]);
        $tipo = TipoUnidadOrganizacional::query()->create([
            'codigo' => 'UNIDAD',
            'nombre' => 'Unidad',
            'activo' => true,
        ]);
        $this->unidad = UnidadOrganizacional::query()->create([
            'codigo' => 'U1',
            'nombre' => 'Unidad Institucional',
            'tipo_unidad_organizacional_id' => $tipo->id,
            'activo' => true,
        ]);
        $this->estamento = Estamento::query()->create([
            'codigo' => 'EST',
            'nombre' => 'Estamento',
            'activo' => true,
        ]);
        $this->calidad = CalidadContractual::query()->create([
            'codigo' => 'CAL',
            'nombre' => 'Calidad',
            'activo' => true,
            'orden' => 1,
        ]);
    }

    public function test_administrador_crea_vinculo_manual_sin_acceso_operativo_artificial(): void
    {
        $this->assertCount(0, $this->admin->accesosOperativos);

        $this->actingAs($this->admin)
            ->get(route('admin.dotacion.create', ['persona_id' => $this->persona->id]))
            ->assertOk()
            ->assertSee($this->unidad->nombre);

        $this->actingAs($this->admin)
            ->post(route('admin.dotacion.store'), $this->datos())
            ->assertRedirect();

        $this->assertDatabaseHas('persona_unidad_vinculos', [
            'persona_id' => $this->persona->id,
            'unidad_organizacional_id' => $this->unidad->id,
            'origen' => OrigenVinculoDotacion::MANUAL->value,
        ]);
    }

    public function test_administrador_edita_y_cierra_vinculo_manual_sin_acceso_operativo(): void
    {
        $vinculo = app(DotacionService::class)->crear($this->datos([
            'vigente_desde' => today()->addDay()->toDateString(),
        ]), $this->admin);

        $this->actingAs($this->admin)
            ->put(route('admin.dotacion.update', $vinculo), $this->datos([
                'cargo_funcion' => 'Cargo actualizado',
                'vigente_desde' => today()->addDay()->toDateString(),
            ]))
            ->assertRedirect(route('admin.dotacion.index'));

        $this->actingAs($this->admin)
            ->patch(route('admin.dotacion.close', $vinculo), [
                'vigente_hasta' => today()->addDay()->toDateString(),
            ])
            ->assertRedirect();

        $this->assertSame('Cargo actualizado', $vinculo->fresh()->cargo_funcion);
        $this->assertSame(today()->addDay()->toDateString(), $vinculo->fresh()->vigente_hasta->toDateString());
    }

    public function test_gestion_personas_conserva_lectura_pero_no_ve_ni_fuerza_acciones_manuales(): void
    {
        $vinculo = app(DotacionService::class)->crear($this->datos(), $this->admin);
        $gestionPersonas = User::factory()->create(['active' => true]);
        $gestionPersonas->assignRole('Gestión de Personas');
        $gestionPersonas->givePermissionTo('dotacion.gestionar');
        $this->darAcceso($gestionPersonas);

        $this->actingAs($gestionPersonas)
            ->get(route('admin.dotacion.index'))
            ->assertOk()
            ->assertSee($vinculo->cargo_funcion)
            ->assertDontSee('Registrar vínculo')
            ->assertDontSee(route('admin.dotacion.edit', $vinculo), false)
            ->assertDontSee('Cerrar vínculo');

        $this->actingAs($gestionPersonas)
            ->get(route('admin.personas.show', $this->persona))
            ->assertOk()
            ->assertDontSee('+ Agregar a dotación');

        $this->actingAs($gestionPersonas)->get(route('admin.dotacion.create'))->assertForbidden();
        $this->actingAs($gestionPersonas)->post(route('admin.dotacion.store'), $this->datos(['cargo_funcion' => 'Forzado GP']))->assertForbidden();
        $this->actingAs($gestionPersonas)->get(route('admin.dotacion.edit', $vinculo))->assertForbidden();
        $this->actingAs($gestionPersonas)->put(route('admin.dotacion.update', $vinculo), $this->datos(['observacion' => 'Forzada']))->assertForbidden();
        $this->actingAs($gestionPersonas)->patch(route('admin.dotacion.close', $vinculo), ['vigente_hasta' => today()->toDateString()])->assertForbidden();

        $this->assertDatabaseMissing('persona_unidad_vinculos', ['cargo_funcion' => 'Forzado GP']);
        $this->assertNull($vinculo->fresh()->vigente_hasta);
    }

    public function test_solicitante_no_obtiene_escritura_manual_aunque_reciba_permiso_y_acceso(): void
    {
        $solicitante = User::factory()->create(['active' => true]);
        $solicitante->assignRole('Solicitante');
        $solicitante->givePermissionTo('dotacion.gestionar');
        $this->darAcceso($solicitante);

        $this->actingAs($solicitante)->get(route('admin.dotacion.create'))->assertForbidden();
        $this->actingAs($solicitante)->post(route('admin.dotacion.store'), $this->datos())->assertForbidden();
        $this->assertDatabaseCount('persona_unidad_vinculos', 0);
    }

    private function datos(array $cambios = []): array
    {
        return [...[
            'persona_id' => $this->persona->id,
            'unidad_organizacional_id' => $this->unidad->id,
            'estamento_id' => $this->estamento->id,
            'profesion_id' => null,
            'calidad_contractual_id' => $this->calidad->id,
            'cargo_funcion' => 'Cargo manual',
            'grado_eus' => null,
            'vigente_desde' => today()->subDay()->toDateString(),
            'vigente_hasta' => null,
            'origen' => OrigenVinculoDotacion::MANUAL->value,
            'observacion' => null,
        ], ...$cambios];
    }

    private function darAcceso(User $user): void
    {
        UserUnidadAcceso::query()->create([
            'user_id' => $user->id,
            'unidad_organizacional_id' => $this->unidad->id,
            'alcance' => AlcanceAccesoOperativo::SOLO_UNIDAD,
            'vigente_desde' => today(),
            'created_by' => $this->admin->id,
        ]);
    }
}
