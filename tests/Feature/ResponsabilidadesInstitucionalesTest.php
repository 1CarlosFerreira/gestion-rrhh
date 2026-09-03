<?php

namespace Tests\Feature;

use App\Enums\TipoResponsabilidad;
use App\Models\Persona;
use App\Models\TipoUnidadOrganizacional;
use App\Models\UnidadOrganizacional;
use App\Models\UnidadResponsable;
use App\Models\User;
use App\Services\Responsabilidades\ResponsabilidadInstitucionalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ResponsabilidadesInstitucionalesTest extends TestCase
{
    use RefreshDatabase;

    private User $actor;

    private UnidadOrganizacional $unidad;

    private ResponsabilidadInstitucionalService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->actor = User::factory()->create();
        $this->actor->assignRole('Administrador');
        $tipo = TipoUnidadOrganizacional::where('codigo', 'UNIDAD')->firstOrFail();
        $this->unidad = UnidadOrganizacional::create(['tipo_unidad_organizacional_id' => $tipo->id, 'codigo' => 'U-1', 'nombre' => 'Unidad Uno', 'activo' => true, 'participa_en_aprobacion' => true, 'orden' => 1]);
        $this->service = app(ResponsabilidadInstitucionalService::class);
    }

    public function test_registers_closed_and_open_holder_periods_and_preserves_audit(): void
    {
        $cerrado = $this->crear(TipoResponsabilidad::TITULAR, '2026-01-01', '2026-01-31');
        $abierto = $this->crear(TipoResponsabilidad::TITULAR, '2026-02-01');
        $this->assertSame($this->actor->id, $cerrado->created_by);
        $this->assertNull($abierto->vigente_hasta);
    }

    public function test_rejects_overlapping_holders_including_open_and_boundary_dates(): void
    {
        $this->crear(TipoResponsabilidad::TITULAR, '2026-01-01', '2026-01-31');
        foreach ([['2026-01-31', '2026-02-10'], ['2025-01-01', null]] as [$inicio, $fin]) {
            try {
                $this->crear(TipoResponsabilidad::TITULAR, $inicio, $fin);
                $this->fail('Debió rechazar el solapamiento inclusivo.');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('vigente_desde', $e->errors());
            }
        }
    }

    public function test_allows_consecutive_holders_without_overlap(): void
    {
        $this->crear(TipoResponsabilidad::TITULAR, '2026-01-01', '2026-01-31');
        $this->crear(TipoResponsabilidad::TITULAR, '2026-02-01', '2026-02-28');
        $this->assertCount(2, UnidadResponsable::all());
    }

    public function test_subrogates_cannot_overlap_but_can_overlap_holder(): void
    {
        $this->crear(TipoResponsabilidad::TITULAR, '2026-01-01');
        $this->crear(TipoResponsabilidad::SUBROGANTE, '2026-02-01', '2026-02-10');
        $this->expectException(ValidationException::class);
        $this->crear(TipoResponsabilidad::SUBROGANTE, '2026-02-05', '2026-02-12');
    }

    public function test_rejects_invalid_dates_and_inactive_unit(): void
    {
        try {
            $this->crear(TipoResponsabilidad::TITULAR, '2026-03-02', '2026-03-01');
            $this->fail();
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('vigente_hasta', $e->errors());
        }
        $this->unidad->update(['activo' => false]);
        $this->expectException(ValidationException::class);
        $this->crear(TipoResponsabilidad::TITULAR, '2026-04-01');
    }

    public function test_subrogate_precedes_holder_and_holder_returns_afterwards(): void
    {
        $titular = $this->crear(TipoResponsabilidad::TITULAR, '2026-01-01');
        $subrogante = $this->crear(TipoResponsabilidad::SUBROGANTE, '2026-02-01', '2026-02-10');
        $this->assertTrue($this->service->responsableVigente($this->unidad, '2026-02-05')->is($subrogante));
        $this->assertTrue($this->service->responsableVigente($this->unidad, '2026-02-11')->is($titular));
        $this->assertNull($this->service->responsableVigente($this->unidad, '2025-12-31'));
    }

    public function test_distinguishes_institutional_from_enabled_responsible(): void
    {
        $responsabilidad = $this->crear(TipoResponsabilidad::TITULAR, '2026-01-01', null, false);
        $this->assertNotNull($this->service->responsableVigente($this->unidad, '2026-05-01'));
        $this->assertNull($this->service->responsableHabilitado($this->unidad, '2026-05-01', true));
        $responsabilidad->update(['puede_aprobar' => true]);
        $this->assertNull($this->service->responsableHabilitado($this->unidad, '2026-05-01', true));
        $user = User::factory()->create(['persona_id' => $responsabilidad->persona_id, 'active' => false]);
        $this->assertNull($this->service->responsableHabilitado($this->unidad, '2026-05-01', true));
        $user->update(['active' => true]);
        $this->assertNotNull($this->service->responsableHabilitado($this->unidad, '2026-05-01', true));
    }

    public function test_finds_first_enabled_approving_ancestor_skips_levels_and_exclusion(): void
    {
        $tipo = $this->unidad->tipo;
        $raiz = UnidadOrganizacional::create(['tipo_unidad_organizacional_id' => $tipo->id, 'codigo' => 'R', 'nombre' => 'Raíz', 'activo' => true, 'participa_en_aprobacion' => true]);
        $medio = UnidadOrganizacional::create(['tipo_unidad_organizacional_id' => $tipo->id, 'codigo' => 'M', 'nombre' => 'Medio', 'parent_id' => $raiz->id, 'activo' => true, 'participa_en_aprobacion' => false]);
        $this->unidad->update(['parent_id' => $medio->id]);
        $persona = $this->persona('22222222-2');
        User::factory()->create(['persona_id' => $persona->id, 'active' => true]);
        $r = $this->crear(TipoResponsabilidad::TITULAR, '2026-01-01', null, true, $raiz, $persona);
        $this->assertTrue($this->service->primerResponsableSuperior($this->unidad, '2026-05-01')->is($r));
        $this->assertNull($this->service->primerResponsableSuperior($this->unidad, '2026-05-01', $persona));
        $raiz->update(['activo' => false]);
        $this->assertNull($this->service->primerResponsableSuperior($this->unidad, '2026-05-01'));
    }

    public function test_authorization_filters_and_no_delete_route(): void
    {
        $r = $this->crear(TipoResponsabilidad::TITULAR, '2026-01-01', '2026-01-31');
        $reader = User::factory()->create();
        $reader->givePermissionTo(Permission::findByName('responsabilidades.ver'));
        $outsider = User::factory()->create();
        $this->actingAs($reader)->get(route('admin.responsabilidades.index', ['persona' => $r->persona->rut, 'tipo' => 'TITULAR', 'unidad_id' => $this->unidad->id, 'fecha' => '2026-01-15']))->assertOk()->assertSee($r->persona->rut)->assertDontSee('Registrar responsable');
        $this->actingAs($reader)->get(route('admin.responsabilidades.edit', $r))->assertForbidden();
        $this->actingAs($outsider)->get(route('admin.responsabilidades.index'))->assertForbidden();
        $this->assertFalse(collect(app('router')->getRoutes()->getRoutes())->contains(fn ($route) => in_array('DELETE', $route->methods(), true) && str_contains($route->uri(), 'responsabilidades')));
    }

    public function test_started_assignment_identity_cannot_change_and_open_period_can_close(): void
    {
        $r = $this->crear(TipoResponsabilidad::TITULAR, '2020-01-01');
        $this->service->cerrar($r, '2026-01-01', $this->actor);
        $this->assertSame('2026-01-01', $r->fresh()->vigente_hasta->toDateString());
        $this->expectException(ValidationException::class);
        $this->service->actualizar($r, ['persona_id' => $this->persona('33333333-3')->id], $this->actor);
    }

    private function crear(TipoResponsabilidad $tipo, string $desde, ?string $hasta = null, bool $aprobar = true, ?UnidadOrganizacional $unidad = null, ?Persona $persona = null): UnidadResponsable
    {
        return $this->service->crear(['unidad_organizacional_id' => ($unidad ?? $this->unidad)->id, 'persona_id' => ($persona ?? $this->persona())->id, 'tipo' => $tipo->value, 'vigente_desde' => $desde, 'vigente_hasta' => $hasta, 'puede_aprobar' => $aprobar, 'observacion' => null], $this->actor);
    }

    private function persona(?string $rut = null): Persona
    {
        static $n = 0;
        $n++;

        return Persona::create(['rut' => $rut ?? "1000000{$n}-K", 'nombres' => "Persona {$n}", 'apellido_paterno' => 'Ficticia']);
    }
}
