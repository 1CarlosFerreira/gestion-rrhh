<?php

namespace Tests\Feature;

use App\Models\TipoUnidadOrganizacional;
use App\Models\UnidadOrganizacional;
use App\Models\User;
use App\Services\EstructuraOrganizacionalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use LogicException;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class EstructuraOrganizacionalTest extends TestCase
{
    use RefreshDatabase;

    private TipoUnidadOrganizacional $tipo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->tipo = TipoUnidadOrganizacional::query()->where('codigo', 'UNIDAD')->firstOrFail();
    }

    public function test_builds_and_queries_a_multilevel_ordered_tree(): void
    {
        $raizB = $this->unidad('B', 'Raíz B', null, 2);
        $raizA = $this->unidad('A', 'Raíz A', null, 1);
        $hijo = $this->unidad('A-1', 'Hijo', $raizA);
        $nieto = $this->unidad('A-1-1', 'Nieto', $hijo);
        $service = app(EstructuraOrganizacionalService::class);

        $this->assertTrue($hijo->parent->is($raizA));
        $this->assertTrue($raizA->children->first()->is($hijo));
        $this->assertSame([$raizA->id, $hijo->id], $service->ancestros($nieto)->pluck('id')->all());
        $this->assertSame([$hijo->id, $nieto->id], $service->descendientes($raizA)->pluck('id')->all());
        $this->assertSame(2, $service->nivel($nieto));
        $this->assertSame('Raíz A / Hijo / Nieto', $service->ruta($nieto));
        $this->assertTrue($service->contiene($raizA, $nieto));
        $this->assertSame([$raizA->id, $raizB->id], UnidadOrganizacional::query()->activas()->raices()->pluck('id')->all());
    }

    public function test_active_children_are_filtered_and_ordered(): void
    {
        $raiz = $this->unidad('R', 'Raíz');
        $this->unidad('I', 'Inactivo', $raiz, 0, false);
        $segundo = $this->unidad('S', 'Segundo', $raiz, 2);
        $primero = $this->unidad('P', 'Primero', $raiz, 1);

        $this->assertSame([$primero->id, $segundo->id], $raiz->activeChildren()->pluck('id')->all());
    }

    public function test_rejects_self_parent_and_moving_below_descendant(): void
    {
        $raiz = $this->unidad('R', 'Raíz');
        $hijo = $this->unidad('H', 'Hijo', $raiz);
        $service = app(EstructuraOrganizacionalService::class);

        foreach ([$raiz, $hijo] as $parent) {
            try {
                $service->validarMovimiento($raiz, $parent);
                $this->fail('Debió rechazar el movimiento.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('parent_id', $exception->errors());
            }
        }
    }

    public function test_cannot_delete_a_node_with_children(): void
    {
        $raiz = $this->unidad('R', 'Raíz');
        $this->unidad('H', 'Hijo', $raiz);
        $this->expectException(LogicException::class);
        $raiz->delete();
    }

    public function test_validation_rejects_duplicate_code_invalid_type_and_invalid_parent(): void
    {
        $admin = $this->admin();
        $this->unidad('DUP', 'Existente');
        $this->actingAs($admin)->post(route('admin.estructura.store'), ['codigo' => 'DUP', 'nombre' => 'Duplicada', 'tipo_unidad_organizacional_id' => 999999, 'parent_id' => 999999, 'orden' => -1])
            ->assertSessionHasErrors(['codigo', 'tipo_unidad_organizacional_id', 'parent_id', 'orden']);
    }

    public function test_permissions_control_read_write_and_actions_visibility(): void
    {
        $unidad = $this->unidad('R', 'Raíz visible');
        $reader = User::factory()->create();
        $reader->givePermissionTo(Permission::findByName('estructura_organizacional.ver'));
        $outsider = User::factory()->create();

        $this->actingAs($this->admin())->get(route('admin.estructura.index'))->assertOk()->assertSee('Crear nodo raíz');
        $this->actingAs($reader)->get(route('admin.estructura.index'))->assertOk()->assertSee('Raíz visible')->assertDontSee('Crear hijo');
        $this->actingAs($reader)->get(route('admin.estructura.edit', $unidad))->assertForbidden();
        $this->actingAs($outsider)->get(route('admin.estructura.index'))->assertForbidden();
    }

    public function test_searches_name_code_and_acronym_and_can_include_inactive_nodes(): void
    {
        $admin = $this->admin();
        $this->unidad('COD-BUSCA', 'Nombre encontrado');
        $this->unidad('INACTIVA', 'Nodo oculto', null, 0, false, 'SIGLA-X');

        foreach (['Nombre encontrado', 'COD-BUSCA'] as $term) {
            $this->actingAs($admin)->get(route('admin.estructura.index', ['buscar' => $term]))->assertOk()->assertSee('Nombre encontrado');
        }
        $this->actingAs($admin)->get(route('admin.estructura.index', ['buscar' => 'SIGLA-X']))->assertDontSee('Nodo oculto');
        $this->actingAs($admin)->get(route('admin.estructura.index', ['buscar' => 'SIGLA-X', 'incluir_inactivos' => 1]))->assertSee('Nodo oculto');
    }

    private function unidad(string $codigo, string $nombre, ?UnidadOrganizacional $parent = null, int $orden = 0, bool $activo = true, ?string $sigla = null): UnidadOrganizacional
    {
        return UnidadOrganizacional::query()->create(['codigo' => $codigo, 'nombre' => $nombre, 'parent_id' => $parent?->id, 'tipo_unidad_organizacional_id' => $this->tipo->id, 'orden' => $orden, 'activo' => $activo, 'sigla' => $sigla]);
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('Administrador');

        return $user;
    }
}
