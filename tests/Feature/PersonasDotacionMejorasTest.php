<?php

namespace Tests\Feature;

use App\Enums\OrigenVinculoDotacion;
use App\Models\CalidadContractual;
use App\Models\Estamento;
use App\Models\Persona;
use App\Models\PersonaUnidadVinculo;
use App\Models\TipoUnidadOrganizacional;
use App\Models\UnidadOrganizacional;
use App\Models\User;
use App\Services\Dotacion\DotacionService;
use Database\Seeders\RolesPermisosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PersonasDotacionMejorasTest extends TestCase
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
        $this->persona = $this->crearPersona('11111111-1', 'Ana', 'Prueba');

        $tipo = TipoUnidadOrganizacional::query()->create([
            'codigo' => 'UNIDAD',
            'nombre' => 'Unidad',
            'activo' => true,
        ]);
        $this->unidad = UnidadOrganizacional::query()->create([
            'codigo' => 'U1',
            'nombre' => 'Unidad Uno',
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

    public function test_listado_de_personas_usa_paginacion_de_25_registros(): void
    {
        foreach (range(1, 25) as $numero) {
            $this->crearPersona(sprintf('%08d-1', $numero + 20000000), 'Persona '.$numero, 'Listado');
        }

        $this->actingAs($this->admin)
            ->get(route('admin.personas.index'))
            ->assertOk()
            ->assertViewHas('personas', function (LengthAwarePaginator $personas): bool {
                return $personas->perPage() === 25
                    && $personas->total() === 26
                    && $personas->count() === 25
                    && $personas->lastPage() === 2;
            });
    }

    public function test_busqueda_por_nombre_y_rut_conserva_filtros_al_paginar(): void
    {
        foreach (range(1, 26) as $numero) {
            $this->crearPersona(sprintf('%08d-2', $numero + 30000000), 'Coincidencia '.$numero, 'Paginada');
        }
        $rutBuscado = '98765432-1';
        $this->crearPersona($rutBuscado, 'Rut', 'Buscado');

        $this->actingAs($this->admin)
            ->get(route('admin.personas.index', ['buscar' => 'Coincidencia', 'page' => 2]))
            ->assertOk()
            ->assertViewHas('personas', function (LengthAwarePaginator $personas): bool {
                return $personas->total() === 26
                    && $personas->currentPage() === 2
                    && $personas->count() === 1
                    && str_contains($personas->url(1), 'buscar=Coincidencia');
            });

        $this->actingAs($this->admin)
            ->get(route('admin.personas.index', ['buscar' => $rutBuscado]))
            ->assertOk()
            ->assertSee($rutBuscado)
            ->assertSee('Rut Buscado');
    }

    public function test_persona_activa_con_vinculo_vigente_no_puede_inactivarse(): void
    {
        $vinculo = $this->crearVinculo();

        $this->actingAs($this->admin)
            ->from(route('admin.personas.show', $this->persona))
            ->patch(route('admin.personas.activo', $this->persona))
            ->assertRedirect(route('admin.personas.show', $this->persona))
            ->assertSessionHasErrors([
                'active' => 'No es posible inactivar a la persona mientras mantenga vínculos laborales vigentes. Cierre primero los vínculos vigentes.',
            ]);

        $this->assertTrue($this->persona->fresh()->active);
        $this->assertDatabaseHas('persona_unidad_vinculos', [
            'id' => $vinculo->id,
            'vigente_hasta' => null,
        ]);
    }

    public function test_persona_sin_vinculos_vigentes_puede_inactivarse(): void
    {
        $this->actingAs($this->admin)
            ->patch(route('admin.personas.activo', $this->persona))
            ->assertRedirect()
            ->assertSessionHas('status', 'Persona inactivada correctamente.');

        $this->assertFalse($this->persona->fresh()->active);
    }

    public function test_persona_con_todos_sus_vinculos_cerrados_puede_inactivarse(): void
    {
        $vinculo = $this->crearVinculo(['vigente_hasta' => today()->subDay()->toDateString()]);

        $this->actingAs($this->admin)
            ->patch(route('admin.personas.activo', $this->persona))
            ->assertRedirect()
            ->assertSessionHas('status', 'Persona inactivada correctamente.');

        $this->assertFalse($this->persona->fresh()->active);
        $this->assertSame(today()->subDay()->toDateString(), $vinculo->fresh()->vigente_hasta->toDateString());
    }

    public function test_persona_inactiva_puede_reactivarse(): void
    {
        $this->persona->update(['active' => false]);

        $this->actingAs($this->admin)
            ->patch(route('admin.personas.activo', $this->persona))
            ->assertRedirect()
            ->assertSessionHas('status', 'Persona reactivada correctamente.');

        $this->assertTrue($this->persona->fresh()->active);
    }

    public function test_puede_cerrar_un_vinculo_vigente_registrando_fecha_de_termino(): void
    {
        $vinculo = $this->crearVinculo();

        $this->actingAs($this->admin)
            ->patch(route('admin.dotacion.close', $vinculo), ['vigente_hasta' => today()->toDateString()])
            ->assertRedirect()
            ->assertSessionHas('status', 'Vínculo cerrado.');

        $vinculo->refresh();
        $this->assertSame(today()->toDateString(), $vinculo->vigente_hasta->toDateString());
        $this->assertSame($this->admin->id, $vinculo->updated_by);
    }

    public function test_no_puede_cerrar_nuevamente_un_vinculo_cerrado(): void
    {
        $vinculo = $this->crearVinculo(['vigente_hasta' => today()->subDay()->toDateString()]);

        $this->actingAs($this->admin)
            ->from(route('admin.personas.show', $this->persona))
            ->patch(route('admin.dotacion.close', $vinculo), ['vigente_hasta' => today()->toDateString()])
            ->assertRedirect(route('admin.personas.show', $this->persona))
            ->assertSessionHasErrors('vigente_hasta');

        $this->assertSame(today()->subDay()->toDateString(), $vinculo->fresh()->vigente_hasta->toDateString());
    }

    public function test_fecha_de_termino_no_puede_ser_anterior_al_inicio(): void
    {
        $vinculo = $this->crearVinculo(['vigente_desde' => today()->toDateString()]);

        $this->actingAs($this->admin)
            ->from(route('admin.personas.show', $this->persona))
            ->patch(route('admin.dotacion.close', $vinculo), ['vigente_hasta' => today()->subDay()->toDateString()])
            ->assertRedirect(route('admin.personas.show', $this->persona))
            ->assertSessionHasErrors('vigente_hasta');

        $this->assertNull($vinculo->fresh()->vigente_hasta);
    }

    public function test_cerrar_vinculo_no_inactiva_a_la_persona(): void
    {
        $vinculo = $this->crearVinculo();

        $this->actingAs($this->admin)
            ->patch(route('admin.dotacion.close', $vinculo), ['vigente_hasta' => today()->toDateString()])
            ->assertRedirect();

        $this->assertTrue($this->persona->fresh()->active);
    }

    public function test_cerrar_un_vinculo_no_modifica_otros_vinculos_de_la_persona(): void
    {
        $vinculoCerrado = $this->crearVinculo();
        $otroVinculo = $this->crearVinculo(['cargo_funcion' => 'Segunda función']);

        $this->actingAs($this->admin)
            ->patch(route('admin.dotacion.close', $vinculoCerrado), ['vigente_hasta' => today()->toDateString()])
            ->assertRedirect();

        $this->assertSame(today()->toDateString(), $vinculoCerrado->fresh()->vigente_hasta->toDateString());
        $this->assertNull($otroVinculo->fresh()->vigente_hasta);
    }

    public function test_backend_rechaza_cambiar_estado_y_cerrar_vinculo_sin_autorizacion_suficiente(): void
    {
        $lector = User::factory()->create(['active' => true]);
        $lector->givePermissionTo('personas.ver');
        $vinculo = $this->crearVinculo();

        $this->actingAs($lector)
            ->patch(route('admin.personas.activo', $this->persona))
            ->assertForbidden();

        Permission::findOrCreate('dotacion.gestionar');
        $lector->givePermissionTo('dotacion.gestionar');

        $this->actingAs($lector)
            ->patch(route('admin.dotacion.close', $vinculo), ['vigente_hasta' => today()->toDateString()])
            ->assertForbidden();

        $this->assertTrue($this->persona->fresh()->active);
        $this->assertNull($vinculo->fresh()->vigente_hasta);
    }

    public function test_endpoints_manuales_rechazan_origen_documento_firmado_y_tramite_de_origen(): void
    {
        $datos = [
            'persona_id' => $this->persona->id,
            'unidad_organizacional_id' => $this->unidad->id,
            'estamento_id' => $this->estamento->id,
            'profesion_id' => null,
            'calidad_contractual_id' => $this->calidad->id,
            'cargo_funcion' => 'Cargo reservado',
            'grado_eus' => null,
            'vigente_desde' => today()->toDateString(),
            'vigente_hasta' => null,
            'origen' => OrigenVinculoDotacion::DOCUMENTO_FIRMADO->value,
            'origen_tramite_id' => 1,
            'observacion' => null,
        ];

        $this->actingAs($this->admin)
            ->post(route('admin.dotacion.store'), $datos)
            ->assertSessionHasErrors(['origen', 'origen_tramite_id']);

        $this->assertDatabaseMissing('persona_unidad_vinculos', ['cargo_funcion' => 'Cargo reservado']);
    }

    private function crearPersona(string $rut, string $nombres, string $apellidoPaterno): Persona
    {
        return Persona::query()->create([
            'rut' => $rut,
            'nombres' => $nombres,
            'apellido_paterno' => $apellidoPaterno,
            'active' => true,
        ]);
    }

    private function crearVinculo(array $cambios = []): PersonaUnidadVinculo
    {
        $datos = [
            'persona_id' => $this->persona->id,
            'unidad_organizacional_id' => $this->unidad->id,
            'estamento_id' => $this->estamento->id,
            'profesion_id' => null,
            'calidad_contractual_id' => $this->calidad->id,
            'cargo_funcion' => 'Cargo base',
            'grado_eus' => null,
            'vigente_desde' => today()->subMonth()->toDateString(),
            'vigente_hasta' => null,
            'origen' => OrigenVinculoDotacion::MANUAL->value,
            'origen_tramite_id' => null,
            'observacion' => null,
        ];

        return app(DotacionService::class)->crear([...$datos, ...$cambios], $this->admin);
    }
}
