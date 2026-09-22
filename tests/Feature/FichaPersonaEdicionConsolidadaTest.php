<?php

namespace Tests\Feature;

use App\Enums\AlcanceAccesoOperativo;
use App\Enums\TipoResponsabilidad;
use App\Models\CalidadContractual;
use App\Models\Estamento;
use App\Models\Persona;
use App\Models\TipoUnidadOrganizacional;
use App\Models\UnidadOrganizacional;
use App\Models\User;
use App\Services\Accesos\AccesoOperativoService;
use App\Services\Dotacion\DotacionService;
use App\Services\Responsabilidades\ResponsabilidadInstitucionalService;
use Database\Seeders\RolesPermisosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class FichaPersonaEdicionConsolidadaTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Persona $persona;

    private User $userPersona;

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
            'rut' => '12345678-9',
            'nombres' => 'Persona',
            'apellido_paterno' => 'Expediente',
            'active' => true,
        ]);
        $this->userPersona = User::factory()->create([
            'persona_id' => $this->persona->id,
            'name' => $this->persona->nombre_completo,
            'rut' => $this->persona->rut,
            'email' => 'expediente@example.test',
            'active' => true,
        ]);
        $this->userPersona->assignRole('Funcionario');
        $tipo = TipoUnidadOrganizacional::query()->create(['codigo' => 'UNIDAD', 'nombre' => 'Unidad', 'activo' => true]);
        $this->unidad = UnidadOrganizacional::query()->create([
            'codigo' => 'U1',
            'nombre' => 'Unidad Expediente',
            'tipo_unidad_organizacional_id' => $tipo->id,
            'activo' => true,
        ]);
        $this->estamento = Estamento::query()->create(['codigo' => 'EST', 'nombre' => 'Profesional', 'activo' => true]);
        $this->calidad = CalidadContractual::query()->create(['codigo' => 'CAL', 'nombre' => 'Contrata', 'activo' => true, 'orden' => 1]);
    }

    public function test_ficha_muestra_dimensiones_vigentes_futuras_e_historicas_y_administra_el_user_correcto(): void
    {
        $dotacion = app(DotacionService::class);
        $dotacion->crear($this->datosVinculo([
            'cargo_funcion' => 'Cargo histórico',
            'vigente_desde' => today()->subMonths(3)->toDateString(),
            'vigente_hasta' => today()->subMonths(2)->toDateString(),
        ]), $this->admin);
        $dotacion->crear($this->datosVinculo([
            'cargo_funcion' => 'Cargo vigente',
            'vigente_desde' => today()->subMonth()->toDateString(),
        ]), $this->admin);
        $dotacion->crear($this->datosVinculo([
            'cargo_funcion' => 'Cargo futuro',
            'vigente_desde' => today()->addMonth()->toDateString(),
        ]), $this->admin);

        $responsabilidades = app(ResponsabilidadInstitucionalService::class);
        $responsabilidades->crear($this->datosResponsabilidad([
            'vigente_desde' => today()->subMonths(3)->toDateString(),
            'vigente_hasta' => today()->subMonths(2)->toDateString(),
        ]), $this->admin);
        $responsabilidades->crear($this->datosResponsabilidad([
            'vigente_desde' => today()->subMonth()->toDateString(),
        ]), $this->admin);

        $accesos = app(AccesoOperativoService::class);
        $accesos->crear($this->datosAcceso([
            'vigente_desde' => today()->subMonths(3)->toDateString(),
            'vigente_hasta' => today()->subMonths(2)->toDateString(),
        ]), $this->admin);
        $accesos->crear($this->datosAcceso([
            'vigente_desde' => today()->subMonth()->toDateString(),
        ]), $this->admin);

        $this->actingAs($this->admin)
            ->get(route('admin.personas.show', $this->persona))
            ->assertOk()
            ->assertSeeInOrder(['Vínculos futuros', 'Cargo futuro', 'Vínculos vigentes', 'Cargo vigente', 'Historial de vínculos', 'Cargo histórico'])
            ->assertSee('Responsabilidades institucionales')
            ->assertSee('Titular · '.$this->unidad->nombre)
            ->assertSee('Ámbito de operación')
            ->assertSee('Solo unidad')
            ->assertSee(route('admin.usuarios.perfil-acceso.edit', $this->userPersona), false)
            ->assertSee('Administrar acceso')
            ->assertSee('+ Agregar autorización adicional')
            ->assertSee('+ Agregar responsabilidad');
    }

    public function test_ediciones_y_cierres_iniciados_desde_ficha_regresan_a_la_misma_persona(): void
    {
        $vinculo = app(DotacionService::class)->crear($this->datosVinculo(['cargo_funcion' => 'Editable']), $this->admin);
        $responsabilidad = app(ResponsabilidadInstitucionalService::class)->crear($this->datosResponsabilidad([
            'unidad_organizacional_id' => $this->otraUnidad()->id,
            'tipo' => TipoResponsabilidad::SUBROGANTE->value,
        ]), $this->admin);
        $acceso = app(AccesoOperativoService::class)->crear($this->datosAcceso([
            'unidad_organizacional_id' => $responsabilidad->unidad_organizacional_id,
        ]), $this->admin);

        $this->actingAs($this->admin)
            ->put(route('admin.dotacion.update', $vinculo), [...$this->datosVinculo(['cargo_funcion' => 'Editable', 'observacion' => 'Actualizado']), 'return_to' => 'persona'])
            ->assertRedirect(route('admin.personas.show', $this->persona));

        $this->actingAs($this->admin)
            ->put(route('admin.responsabilidades.update', $responsabilidad), [...$this->datosResponsabilidad([
                'unidad_organizacional_id' => $responsabilidad->unidad_organizacional_id,
                'tipo' => TipoResponsabilidad::SUBROGANTE->value,
                'observacion' => 'Actualizada',
            ]), 'return_to' => 'persona'])
            ->assertRedirect(route('admin.personas.show', $this->persona));

        $this->actingAs($this->admin)
            ->put(route('admin.accesos.update', $acceso), [...$this->datosAcceso([
                'unidad_organizacional_id' => $acceso->unidad_organizacional_id,
                'observacion' => 'Actualizado',
            ]), 'return_to' => 'persona'])
            ->assertRedirect(route('admin.personas.show', $this->persona));

        $this->actingAs($this->admin)
            ->patch(route('admin.dotacion.close', $vinculo), ['vigente_hasta' => today()->toDateString(), 'return_to' => 'persona'])
            ->assertRedirect(route('admin.personas.show', $this->persona));
        $this->actingAs($this->admin)
            ->patch(route('admin.responsabilidades.close', $responsabilidad), ['vigente_hasta' => today()->toDateString(), 'return_to' => 'persona'])
            ->assertRedirect(route('admin.personas.show', $this->persona));
        $this->actingAs($this->admin)
            ->patch(route('admin.accesos.close', $acceso), ['vigente_hasta' => today()->toDateString(), 'return_to' => 'persona'])
            ->assertRedirect(route('admin.personas.show', $this->persona));
    }

    public function test_return_to_no_admite_redirect_arbitrario(): void
    {
        $vinculo = app(DotacionService::class)->crear($this->datosVinculo(), $this->admin);

        $this->actingAs($this->admin)
            ->put(route('admin.dotacion.update', $vinculo), [...$this->datosVinculo(['observacion' => 'Sin redirect abierto']), 'return_to' => 'https://evil.example'])
            ->assertRedirect(route('admin.dotacion.index'));
    }

    public function test_cierre_laboral_se_bloquea_si_responsabilidad_abierta_queda_fuera_y_no_la_cierra(): void
    {
        $vinculo = app(DotacionService::class)->crear($this->datosVinculo([
            'vigente_desde' => today()->subMonth()->toDateString(),
        ]), $this->admin);
        $responsabilidad = app(ResponsabilidadInstitucionalService::class)->crear($this->datosResponsabilidad([
            'vigente_desde' => today()->subWeek()->toDateString(),
        ]), $this->admin);

        $this->actingAs($this->admin)
            ->from(route('admin.personas.show', $this->persona))
            ->patch(route('admin.dotacion.close', $vinculo), ['vigente_hasta' => today()->toDateString(), 'return_to' => 'persona'])
            ->assertRedirect(route('admin.personas.show', $this->persona))
            ->assertSessionHasErrors('responsabilidad_incompatible');

        $this->assertNull($vinculo->fresh()->vigente_hasta);
        $this->assertNull($responsabilidad->fresh()->vigente_hasta);
    }

    public function test_cierre_laboral_se_permite_si_responsabilidad_termina_dentro_del_periodo(): void
    {
        $vinculo = app(DotacionService::class)->crear($this->datosVinculo([
            'vigente_desde' => today()->subMonth()->toDateString(),
        ]), $this->admin);
        $responsabilidad = app(ResponsabilidadInstitucionalService::class)->crear($this->datosResponsabilidad([
            'vigente_desde' => today()->subWeeks(2)->toDateString(),
            'vigente_hasta' => today()->subDay()->toDateString(),
        ]), $this->admin);

        $this->actingAs($this->admin)
            ->patch(route('admin.dotacion.close', $vinculo), ['vigente_hasta' => today()->toDateString(), 'return_to' => 'persona'])
            ->assertRedirect(route('admin.personas.show', $this->persona));

        $this->assertSame(today()->toDateString(), $vinculo->fresh()->vigente_hasta->toDateString());
        $this->assertSame(today()->subDay()->toDateString(), $responsabilidad->fresh()->vigente_hasta->toDateString());
    }

    public function test_subrogancia_sin_termino_no_bloquea_el_cierre_laboral(): void
    {
        $vinculo = app(DotacionService::class)->crear($this->datosVinculo(), $this->admin);
        $responsabilidad = app(ResponsabilidadInstitucionalService::class)->crear($this->datosResponsabilidad([
            'tipo' => TipoResponsabilidad::SUBROGANTE->value,
            'vigente_desde' => today()->subWeek()->toDateString(),
        ]), $this->admin);

        $this->actingAs($this->admin)
            ->patch(route('admin.dotacion.close', $vinculo), ['vigente_hasta' => today()->toDateString(), 'return_to' => 'persona'])
            ->assertRedirect(route('admin.personas.show', $this->persona))
            ->assertSessionDoesntHaveErrors();

        $this->assertSame(today()->toDateString(), $vinculo->fresh()->vigente_hasta->toDateString());
        $this->assertNull($responsabilidad->fresh()->vigente_hasta);
    }

    public function test_acortar_vinculo_desde_edicion_se_bloquea_si_excede_responsabilidad(): void
    {
        $vinculo = app(DotacionService::class)->crear($this->datosVinculo([
            'vigente_hasta' => today()->addMonth()->toDateString(),
        ]), $this->admin);
        app(ResponsabilidadInstitucionalService::class)->crear($this->datosResponsabilidad([
            'vigente_hasta' => today()->addWeeks(2)->toDateString(),
        ]), $this->admin);

        $this->actingAs($this->admin)
            ->put(route('admin.dotacion.update', $vinculo), $this->datosVinculo([
                'vigente_hasta' => today()->toDateString(),
            ]))
            ->assertSessionHasErrors('responsabilidad_incompatible');

        $this->assertSame(today()->addMonth()->toDateString(), $vinculo->fresh()->vigente_hasta->toDateString());
    }

    public function test_cierre_laboral_no_bloquea_si_otro_vinculo_cubre_toda_la_titularidad(): void
    {
        $vinculoACerrar = app(DotacionService::class)->crear($this->datosVinculo([
            'cargo_funcion' => 'Primer cargo',
            'vigente_desde' => today()->subMonth()->toDateString(),
        ]), $this->admin);
        app(DotacionService::class)->crear($this->datosVinculo([
            'cargo_funcion' => 'Segundo cargo',
            'vigente_desde' => today()->subMonth()->toDateString(),
        ]), $this->admin);
        $responsabilidad = app(ResponsabilidadInstitucionalService::class)->crear($this->datosResponsabilidad([
            'vigente_desde' => today()->subWeek()->toDateString(),
        ]), $this->admin);

        $this->actingAs($this->admin)
            ->patch(route('admin.dotacion.close', $vinculoACerrar), ['vigente_hasta' => today()->toDateString(), 'return_to' => 'persona'])
            ->assertRedirect(route('admin.personas.show', $this->persona))
            ->assertSessionDoesntHaveErrors();

        $this->assertSame(today()->toDateString(), $vinculoACerrar->fresh()->vigente_hasta->toDateString());
        $this->assertNull($responsabilidad->fresh()->vigente_hasta);
    }

    public function test_no_se_pueden_reabrir_registros_finalizados_desde_edicion_general(): void
    {
        $vinculo = app(DotacionService::class)->crear($this->datosVinculo([
            'vigente_desde' => today()->subMonth()->toDateString(),
            'vigente_hasta' => today()->subDay()->toDateString(),
        ]), $this->admin);
        $responsabilidad = app(ResponsabilidadInstitucionalService::class)->crear($this->datosResponsabilidad([
            'unidad_organizacional_id' => $this->otraUnidad()->id,
            'tipo' => TipoResponsabilidad::SUBROGANTE->value,
            'vigente_desde' => today()->subMonth()->toDateString(),
            'vigente_hasta' => today()->subDay()->toDateString(),
        ]), $this->admin);
        $acceso = app(AccesoOperativoService::class)->crear($this->datosAcceso([
            'unidad_organizacional_id' => $responsabilidad->unidad_organizacional_id,
            'vigente_desde' => today()->subMonth()->toDateString(),
            'vigente_hasta' => today()->subDay()->toDateString(),
        ]), $this->admin);

        foreach ([
            fn () => app(DotacionService::class)->actualizar($vinculo, ['vigente_hasta' => null], $this->admin),
            fn () => app(ResponsabilidadInstitucionalService::class)->actualizar($responsabilidad, ['vigente_hasta' => null], $this->admin),
            fn () => app(AccesoOperativoService::class)->actualizar($acceso, ['vigente_hasta' => null], $this->admin),
        ] as $reabrir) {
            try {
                $reabrir();
                $this->fail('El registro finalizado no debe reabrirse.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('vigente_hasta', $exception->errors());
            }
        }

        $this->assertNotNull($vinculo->fresh()->vigente_hasta);
        $this->assertNotNull($responsabilidad->fresh()->vigente_hasta);
        $this->assertNotNull($acceso->fresh()->vigente_hasta);
    }

    private function datosVinculo(array $cambios = []): array
    {
        return [...[
            'persona_id' => $this->persona->id,
            'unidad_organizacional_id' => $this->unidad->id,
            'estamento_id' => $this->estamento->id,
            'profesion_id' => null,
            'calidad_contractual_id' => $this->calidad->id,
            'cargo_funcion' => 'Cargo base',
            'grado_eus' => null,
            'vigente_desde' => today()->subMonth()->toDateString(),
            'vigente_hasta' => null,
            'origen' => 'MANUAL',
            'observacion' => null,
        ], ...$cambios];
    }

    private function datosResponsabilidad(array $cambios = []): array
    {
        return [...[
            'unidad_organizacional_id' => $this->unidad->id,
            'persona_id' => $this->persona->id,
            'tipo' => TipoResponsabilidad::TITULAR->value,
            'vigente_desde' => today()->subMonth()->toDateString(),
            'vigente_hasta' => null,
            'puede_aprobar' => true,
            'observacion' => null,
        ], ...$cambios];
    }

    private function datosAcceso(array $cambios = []): array
    {
        return [...[
            'user_id' => $this->userPersona->id,
            'unidad_organizacional_id' => $this->unidad->id,
            'alcance' => AlcanceAccesoOperativo::SOLO_UNIDAD->value,
            'vigente_desde' => today()->subMonth()->toDateString(),
            'vigente_hasta' => null,
            'observacion' => null,
        ], ...$cambios];
    }

    private function otraUnidad(): UnidadOrganizacional
    {
        return UnidadOrganizacional::query()->firstOrCreate([
            'codigo' => 'U2',
        ], [
            'nombre' => 'Unidad Alternativa',
            'tipo_unidad_organizacional_id' => $this->unidad->tipo_unidad_organizacional_id,
            'activo' => true,
        ]);
    }
}
