<?php

namespace Tests\Feature;

use App\Enums\AlcanceAccesoOperativo;
use App\Enums\EstadoVinculoDotacion;
use App\Enums\OrigenVinculoDotacion;
use App\Models\CalidadContractual;
use App\Models\EstadoTramite;
use App\Models\Estamento;
use App\Models\Persona;
use App\Models\PersonaUnidadVinculo;
use App\Models\TipoTramite;
use App\Models\TipoUnidadOrganizacional;
use App\Models\Tramite;
use App\Models\UnidadOrganizacional;
use App\Models\UnidadResponsable;
use App\Models\User;
use App\Models\UserUnidadAcceso;
use App\Services\Dotacion\DotacionService;
use Database\Seeders\RolesPermisosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class DotacionHistoricaTest extends TestCase
{
    use RefreshDatabase;

    private User $actor;

    private Persona $persona;

    private UnidadOrganizacional $unidad;

    private Estamento $estamento;

    private CalidadContractual $calidad;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actor = User::factory()->create(['active' => true]);
        $this->persona = Persona::query()->create(['rut' => '11111111-1', 'nombres' => 'Ana', 'apellido_paterno' => 'Prueba', 'active' => true]);
        $tipo = TipoUnidadOrganizacional::query()->create(['codigo' => 'UNIDAD', 'nombre' => 'Unidad', 'activo' => true]);
        $this->unidad = UnidadOrganizacional::query()->create(['codigo' => 'U1', 'nombre' => 'Unidad Uno', 'tipo_unidad_organizacional_id' => $tipo->id, 'activo' => true]);
        $this->estamento = Estamento::query()->create(['codigo' => 'EST', 'nombre' => 'Estamento', 'activo' => true]);
        $this->calidad = CalidadContractual::query()->create(['codigo' => 'CAL', 'nombre' => 'Calidad', 'activo' => true, 'orden' => 1]);
    }

    public function test_creates_current_future_and_closed_links_and_calculates_inclusive_status(): void
    {
        $service = app(DotacionService::class);
        $vigente = $service->crear($this->datos(['vigente_desde' => '2026-01-01']), $this->actor);
        $futuro = $service->crear($this->datos(['cargo_funcion' => 'Otra función', 'vigente_desde' => '2027-01-01']), $this->actor);
        $cerrado = $service->crear($this->datos(['cargo_funcion' => 'Cargo histórico', 'vigente_desde' => '2025-01-01', 'vigente_hasta' => '2025-12-31']), $this->actor);
        $this->assertSame(EstadoVinculoDotacion::VIGENTE, $vigente->estadoEn('2026-01-01'));
        $this->assertSame(EstadoVinculoDotacion::FUTURO, $futuro->estadoEn('2026-01-01'));
        $this->assertSame(EstadoVinculoDotacion::VIGENTE, $cerrado->estadoEn('2025-12-31'));
        $this->assertSame(EstadoVinculoDotacion::FINALIZADO, $cerrado->estadoEn('2026-01-01'));
    }

    public function test_rejects_invalid_dates_inactive_references_and_mismatched_profession(): void
    {
        $service = app(DotacionService::class);
        $unidadInactiva = $this->otraUnidad();
        $unidadInactiva->update(['activo' => false]);
        $calidadInactiva = CalidadContractual::query()->create(['codigo' => 'OFF', 'nombre' => 'Off', 'activo' => false]);
        foreach ([['vigente_hasta' => '2025-12-31'], ['unidad_organizacional_id' => $unidadInactiva->id], ['calidad_contractual_id' => $calidadInactiva->id]] as $cambio) {
            try {
                $service->crear($this->datos($cambio), $this->actor);
                $this->fail('Debió rechazar datos inválidos.');
            } catch (ValidationException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_allows_labor_multiplicity_but_rejects_equivalent_overlaps_and_open_periods(): void
    {
        $service = app(DotacionService::class);
        $service->crear($this->datos(['vigente_desde' => '2026-01-01', 'vigente_hasta' => '2026-01-31']), $this->actor);
        foreach ([['unidad_organizacional_id' => $this->otraUnidad()->id], ['calidad_contractual_id' => CalidadContractual::query()->create(['codigo' => 'CAL2', 'nombre' => 'Otra', 'activo' => true])->id], ['cargo_funcion' => 'Cargo diferente']] as $cambio) {
            $this->assertInstanceOf(PersonaUnidadVinculo::class, $service->crear($this->datos($cambio), $this->actor));
        }
        $this->assertInstanceOf(PersonaUnidadVinculo::class, $service->crear($this->datos(['vigente_desde' => '2026-02-01']), $this->actor));
        $this->expectException(ValidationException::class);
        $service->crear($this->datos(['cargo_funcion' => '  cargo   base ', 'vigente_desde' => '2026-03-01']), $this->actor);
    }

    public function test_link_is_separate_from_user_roles_access_responsibility_and_tramite(): void
    {
        $before = [User::count(), UserUnidadAcceso::count(), UnidadResponsable::count(), Tramite::count()];
        app(DotacionService::class)->crear($this->datos(), $this->actor);
        $this->assertSame($before, [User::count(), UserUnidadAcceso::count(), UnidadResponsable::count(), Tramite::count()]);
        $this->assertNull($this->persona->fresh()->user);
        $this->assertCount(0, $this->actor->getRoleNames());
    }

    public function test_document_origin_requires_tramite_and_is_idempotent(): void
    {
        $service = app(DotacionService::class);
        try {
            $service->crear($this->datos(['origen' => OrigenVinculoDotacion::DOCUMENTO_FIRMADO->value]), $this->actor);
            $this->fail();
        } catch (ValidationException) {
            $this->assertSame(0, PersonaUnidadVinculo::count());
        }
        $tramite = $this->tramite();
        $primero = $service->crearDesdeDocumentoFirmado($tramite, $this->datos(), $this->actor);
        $segundo = $service->crearDesdeDocumentoFirmado($tramite, $this->datos(), $this->actor);
        $this->assertTrue($primero->is($segundo));
        $this->assertSame(1, PersonaUnidadVinculo::count());
    }

    public function test_queries_current_descendants_history_upcoming_membership_and_units(): void
    {
        $hija = $this->otraUnidad($this->unidad);
        $service = app(DotacionService::class);
        $v = $service->crear($this->datos(['unidad_organizacional_id' => $hija->id]), $this->actor);
        $service->crear($this->datos(['cargo_funcion' => 'Futuro', 'vigente_desde' => '2027-01-01']), $this->actor);
        $this->assertCount(1, $service->dotacionVigente($this->unidad, '2026-06-01', true));
        $this->assertCount(0, $service->dotacionVigente($this->unidad, '2026-06-01', false));
        $this->assertTrue($service->pertenece($this->persona, $hija, '2026-06-01'));
        $this->assertTrue($service->unidadesVigentes($this->persona, '2026-06-01')->contains($hija));
        $this->assertCount(2, $service->historicosPersona($this->persona));
        $this->assertCount(1, $service->proximasIncorporaciones(null, '2026-06-01'));
        $this->assertTrue($v->exists);
    }

    public function test_started_identity_is_immutable_observation_can_change_and_open_link_can_close(): void
    {
        $service = app(DotacionService::class);
        $vinculo = $service->crear($this->datos(['vigente_desde' => today()->subDay()->toDateString()]), $this->actor);
        $service->actualizar($vinculo, ['observacion' => 'Corregida'], $this->actor);
        $service->cerrar($vinculo, today()->toDateString(), $this->actor);
        $this->assertSame('Corregida', $vinculo->fresh()->observacion);
        $this->assertNotNull($vinculo->fresh()->updated_by);
        $this->expectException(ValidationException::class);
        $service->actualizar($vinculo, ['cargo_funcion' => 'Reescrito'], $this->actor);
    }

    public function test_permissions_and_operational_scope_control_backend_including_descendants(): void
    {
        foreach (['dotacion.ver', 'dotacion.gestionar'] as $p) {
            Permission::findOrCreate($p);
        }
        $hija = $this->otraUnidad($this->unidad);
        $user = User::factory()->create(['active' => true, 'persona_id' => Persona::query()->create(['rut' => '22222222-2', 'nombres' => 'Usuario', 'active' => true])->id]);
        $user->givePermissionTo(['dotacion.ver', 'dotacion.gestionar']);
        UserUnidadAcceso::query()->create(['user_id' => $user->id, 'unidad_organizacional_id' => $this->unidad->id, 'alcance' => AlcanceAccesoOperativo::UNIDAD_Y_DESCENDIENTES, 'vigente_desde' => today(), 'created_by' => $this->actor->id]);
        $vinculo = app(DotacionService::class)->crear($this->datos(['unidad_organizacional_id' => $hija->id]), $this->actor);
        $this->assertTrue($user->can('view', $vinculo));
        $this->actingAs($user)->get(route('admin.dotacion.index', ['unidad_id' => $hija->id]))->assertOk()->assertSee('Cargo base');
        $fuera = app(DotacionService::class)->crear($this->datos(['unidad_organizacional_id' => $this->otraUnidad()->id, 'cargo_funcion' => 'Fuera']), $this->actor);
        $this->assertFalse($user->can('view', $fuera));
        $user->revokePermissionTo('dotacion.ver');
        $this->actingAs($user)->get(route('admin.dotacion.index'))->assertForbidden();
    }

    public function test_permission_without_access_access_without_permission_expired_inactive_and_responsibility_do_not_authorize(): void
    {
        Permission::findOrCreate('dotacion.ver');
        $user = User::factory()->create(['active' => true]);
        $user->givePermissionTo('dotacion.ver');
        $this->actingAs($user)->get(route('admin.dotacion.index'))->assertForbidden();
        $user->revokePermissionTo('dotacion.ver');
        UserUnidadAcceso::query()->create(['user_id' => $user->id, 'unidad_organizacional_id' => $this->unidad->id, 'alcance' => AlcanceAccesoOperativo::SOLO_UNIDAD, 'vigente_desde' => '2025-01-01', 'vigente_hasta' => '2025-12-31', 'created_by' => $this->actor->id]);
        $this->actingAs($user)->get(route('admin.dotacion.index'))->assertForbidden();
        $user->givePermissionTo('dotacion.ver');
        $user->update(['active' => false]);
        $this->actingAs($user)->get(route('admin.dotacion.index'))->assertForbidden();
    }

    public function test_admin_manages_catalog_and_dotacion_has_no_delete_routes(): void
    {
        $this->seed(RolesPermisosSeeder::class);
        $admin = User::factory()->create(['active' => true]);
        $admin->assignRole('Administrador');
        $this->actingAs($admin)->get(route('admin.calidades.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.dotacion.index'))->assertOk();
        $this->assertFalse(collect(app('router')->getRoutes())->contains(fn ($route) => in_array('DELETE', $route->methods()) && str_contains($route->uri(), 'dotacion')));
    }

    private function datos(array $cambios = []): array
    {
        return [...['persona_id' => $this->persona->id, 'unidad_organizacional_id' => $this->unidad->id, 'estamento_id' => $this->estamento->id, 'profesion_id' => null, 'calidad_contractual_id' => $this->calidad->id, 'cargo_funcion' => 'Cargo base', 'grado_eus' => null, 'vigente_desde' => '2026-01-01', 'vigente_hasta' => null, 'origen' => OrigenVinculoDotacion::MANUAL->value, 'origen_tramite_id' => null, 'observacion' => null], ...$cambios];
    }

    private function otraUnidad(?UnidadOrganizacional $padre = null): UnidadOrganizacional
    {
        return UnidadOrganizacional::query()->create(['codigo' => Str::upper(Str::random(6)), 'nombre' => 'Unidad '.Str::random(5), 'tipo_unidad_organizacional_id' => $this->unidad->tipo_unidad_organizacional_id, 'parent_id' => $padre?->id, 'activo' => true]);
    }

    private function tramite(): Tramite
    {
        $tipo = TipoTramite::query()->create(['codigo' => 'TEST', 'nombre' => 'Test', 'activo' => true]);
        $estado = EstadoTramite::query()->create(['tipo_tramite_id' => $tipo->id, 'codigo' => 'BORRADOR', 'nombre' => 'Borrador', 'orden' => 1, 'es_inicial' => true]);

        return Tramite::query()->create(['public_id' => (string) Str::ulid(), 'codigo' => 'T-1', 'tipo_tramite_id' => $tipo->id, 'estado_tramite_id' => $estado->id, 'created_by' => $this->actor->id]);
    }
}
