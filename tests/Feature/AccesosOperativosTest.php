<?php

namespace Tests\Feature;

use App\Enums\AlcanceAccesoOperativo;
use App\Models\Persona;
use App\Models\TipoUnidadOrganizacional;
use App\Models\UnidadOrganizacional;
use App\Models\User;
use App\Models\UserUnidadAcceso;
use App\Services\Accesos\AccesoOperativoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AccesosOperativosTest extends TestCase
{
    use RefreshDatabase;

    private User $actor;

    private User $user;

    private UnidadOrganizacional $root;

    private UnidadOrganizacional $child;

    private UnidadOrganizacional $grandchild;

    private UnidadOrganizacional $sibling;

    private AccesoOperativoService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->actor = User::factory()->create();
        $this->actor->assignRole('Administrador');
        $persona = Persona::create(['rut' => '12345678-5', 'nombres' => 'Operadora', 'apellido_paterno' => 'Ficticia']);
        $this->user = User::factory()->create(['persona_id' => $persona->id]);
        $tipo = TipoUnidadOrganizacional::where('codigo', 'UNIDAD')->first();
        $this->root = $this->unit('R', 'Raíz', $tipo);
        $this->child = $this->unit('H', 'Hija', $tipo, $this->root);
        $this->grandchild = $this->unit('N', 'Nieta', $tipo, $this->child);
        $this->sibling = $this->unit('S', 'Hermana', $tipo, $this->root);
        $this->service = app(AccesoOperativoService::class);
    }

    public function test_creates_closed_and_open_access_and_preserves_audit(): void
    {
        $a = $this->create(AlcanceAccesoOperativo::SOLO_UNIDAD, '2026-01-01', '2026-01-31');
        $b = $this->create(AlcanceAccesoOperativo::SOLO_UNIDAD, '2026-02-01');
        $this->assertSame($this->actor->id, $a->created_by);
        $this->assertNull($b->vigente_hasta);
        $this->assertCount(2, $this->service->historicos($this->user));
    }

    public function test_rejects_invalid_overlap_duplicates_open_period_and_inclusive_boundary(): void
    {
        $this->create(AlcanceAccesoOperativo::SOLO_UNIDAD, '2026-01-01', '2026-01-31');
        foreach ([['2026-01-31', '2026-02-02'], ['2025-01-01', null]] as [$from, $to]) {
            try {
                $this->create(AlcanceAccesoOperativo::SOLO_UNIDAD, $from, $to);
                $this->fail();
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('vigente_desde', $e->errors());
            }
        }
        $this->expectException(ValidationException::class);
        $this->create(AlcanceAccesoOperativo::UNIDAD_Y_DESCENDIENTES, '2026-02-02', '2026-02-01');
    }

    public function test_separate_periods_and_future_expired_and_boundaries(): void
    {
        $this->create(AlcanceAccesoOperativo::SOLO_UNIDAD, '2026-01-01', '2026-01-31');
        $this->create(AlcanceAccesoOperativo::SOLO_UNIDAD, '2026-02-01', '2026-02-28');
        $this->assertTrue($this->service->tieneAcceso($this->user, $this->root, '2026-01-01'));
        $this->assertTrue($this->service->tieneAcceso($this->user, $this->root, '2026-01-31'));
        $this->assertFalse($this->service->tieneAcceso($this->user, $this->root, '2025-12-31'));
        $this->assertFalse($this->service->tieneAcceso($this->user, $this->root, '2026-03-01'));
    }

    public function test_exact_scope_excludes_children_ancestors_and_siblings(): void
    {
        $this->create(AlcanceAccesoOperativo::SOLO_UNIDAD, '2026-01-01', null, $this->child);
        $this->assertTrue($this->service->tieneAcceso($this->user, $this->child, '2026-05-01'));
        $this->assertFalse($this->service->tieneAcceso($this->user, $this->grandchild, '2026-05-01'));
        $this->assertFalse($this->service->tieneAcceso($this->user, $this->root, '2026-05-01'));
        $this->assertFalse($this->service->tieneAcceso($this->user, $this->sibling, '2026-05-01'));
    }

    public function test_descendant_scope_covers_multiple_levels_without_ancestors_or_siblings_and_deduplicates(): void
    {
        $this->create(AlcanceAccesoOperativo::UNIDAD_Y_DESCENDIENTES, '2026-01-01', null, $this->child);
        $this->create(AlcanceAccesoOperativo::SOLO_UNIDAD, '2026-01-01', null, $this->grandchild);
        $ids = $this->service->unidadesAccesibles($this->user, '2026-05-01')->pluck('id');
        $this->assertEqualsCanonicalizing([$this->child->id, $this->grandchild->id], $ids->all());
        $this->assertFalse($ids->contains($this->root->id));
        $this->assertFalse($ids->contains($this->sibling->id));
    }

    public function test_inactive_user_or_units_do_not_authorize_but_history_remains(): void
    {
        $this->create(AlcanceAccesoOperativo::UNIDAD_Y_DESCENDIENTES, '2026-01-01');
        $this->grandchild->update(['activo' => false]);
        $this->assertFalse($this->service->tieneAcceso($this->user, $this->grandchild, '2026-05-01'));
        $this->root->update(['activo' => false]);
        $this->assertFalse($this->service->tieneAcceso($this->user, $this->child, '2026-05-01'));
        $this->user->update(['active' => false]);
        $this->assertCount(1, $this->service->historicos($this->user));
        $this->assertEmpty($this->service->accesosVigentes($this->user, '2026-05-01'));
    }

    public function test_tree_move_changes_descendant_access_dynamically(): void
    {
        $this->create(AlcanceAccesoOperativo::UNIDAD_Y_DESCENDIENTES, '2026-01-01', null, $this->child);
        $this->assertFalse($this->service->tieneAcceso($this->user, $this->sibling, '2026-05-01'));
        $this->sibling->update(['parent_id' => $this->child->id]);
        $this->assertTrue($this->service->tieneAcceso($this->user, $this->sibling, '2026-05-01'));
    }

    public function test_requires_active_user_with_person_and_does_not_grant_roles_permissions_or_responsibility(): void
    {
        $without = User::factory()->create(['persona_id' => null]);
        try {
            $this->service->crear($this->data(AlcanceAccesoOperativo::SOLO_UNIDAD, '2026-01-01', null, $this->root, $without), $this->actor);
            $this->fail();
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('user_id', $e->errors());
        }
        $this->create(AlcanceAccesoOperativo::SOLO_UNIDAD, '2026-01-01');
        $this->assertEmpty($this->user->getRoleNames());
        $this->assertEmpty($this->user->getAllPermissions());
        $this->assertDatabaseCount('unidad_responsables', 0);
    }

    public function test_permission_and_access_are_both_required(): void
    {
        $this->create(AlcanceAccesoOperativo::SOLO_UNIDAD, '2026-01-01');
        $this->assertFalse($this->service->tienePermisoYAcceso($this->user, 'accesos_operativos.ver', $this->root, '2026-05-01'));
        $this->user->givePermissionTo('accesos_operativos.ver');
        $this->assertTrue($this->service->tienePermisoYAcceso($this->user, 'accesos_operativos.ver', $this->root, '2026-05-01'));
    }

    public function test_admin_authorization_read_only_and_no_delete_route(): void
    {
        $access = $this->create(AlcanceAccesoOperativo::SOLO_UNIDAD, '2026-01-01');
        $reader = User::factory()->create();
        $reader->givePermissionTo(Permission::findByName('accesos_operativos.ver'));
        $outsider = User::factory()->create();
        $this->actingAs($this->actor)->get(route('admin.accesos.index'))->assertOk()->assertSee('Registrar acceso');
        $this->actingAs($reader)->get(route('admin.accesos.index'))->assertOk()->assertDontSee('Registrar acceso');
        $this->actingAs($reader)->get(route('admin.accesos.edit', $access))->assertForbidden();
        $this->actingAs($outsider)->get(route('admin.accesos.index'))->assertForbidden();
        $this->assertFalse(collect(app('router')->getRoutes()->getRoutes())->contains(fn ($r) => in_array('DELETE', $r->methods(), true) && str_contains($r->uri(), 'accesos-operativos')));
    }

    public function test_started_identity_is_immutable_and_open_access_can_close(): void
    {
        $a = $this->create(AlcanceAccesoOperativo::SOLO_UNIDAD, '2020-01-01');
        $this->service->cerrarAcceso($a, '2026-01-01', $this->actor);
        $this->assertSame('2026-01-01', $a->fresh()->vigente_hasta->toDateString());
        $this->expectException(ValidationException::class);
        $this->service->actualizar($a, ['unidad_organizacional_id' => $this->child->id], $this->actor);
    }

    private function create(AlcanceAccesoOperativo $scope, string $from, ?string $to = null, ?UnidadOrganizacional $unit = null): UserUnidadAcceso
    {
        return $this->service->crear($this->data($scope, $from, $to, $unit), $this->actor);
    }

    private function data(AlcanceAccesoOperativo $scope, string $from, ?string $to, ?UnidadOrganizacional $unit = null, ?User $user = null): array
    {
        return ['user_id' => ($user ?? $this->user)->id, 'unidad_organizacional_id' => ($unit ?? $this->root)->id, 'alcance' => $scope->value, 'vigente_desde' => $from, 'vigente_hasta' => $to, 'observacion' => null];
    }

    private function unit(string $code, string $name, $type, ?UnidadOrganizacional $parent = null): UnidadOrganizacional
    {
        return UnidadOrganizacional::create(['tipo_unidad_organizacional_id' => $type->id, 'codigo' => $code, 'nombre' => $name, 'parent_id' => $parent?->id, 'activo' => true, 'orden' => 1]);
    }
}
