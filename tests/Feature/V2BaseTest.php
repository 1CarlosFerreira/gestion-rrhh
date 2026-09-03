<?php

namespace Tests\Feature;

use App\Models\Tramite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class V2BaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_clean_schema_preserves_transversal_tables_and_removes_v1_domains(): void
    {
        foreach (['users', 'personas', 'roles', 'permissions', 'tramites', 'tramite_historial', 'tramite_adjuntos', 'documento_plantillas', 'documentos_generados', 'calidades_contractuales', 'persona_unidad_vinculos'] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Falta la tabla transversal {$table}.");
        }

        foreach (['unidades_servicios', 'user_unidades', 'grados_eus', 'tramite_reemplazos', 'tramite_horas_extra', 'docdigital_registros'] as $table) {
            $this->assertFalse(Schema::hasTable($table), "La tabla v1 {$table} no debe existir.");
        }
    }

    public function test_administrator_can_manage_multiple_roles_and_see_their_permissions(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'admin@example.test')->firstOrFail();
        $target = User::factory()->create();
        $roles = Role::query()->whereIn('name', ['Funcionario', 'Jefatura'])->pluck('name')->all();

        $this->actingAs($admin)
            ->put(route('admin.usuarios.roles.update', $target), ['roles' => $roles])
            ->assertRedirect();

        $this->assertTrue($target->fresh()->hasAllRoles($roles));
        $this->actingAs($admin)->get(route('admin.usuarios.index'))
            ->assertOk()
            ->assertSee('admin.roles_permisos');
    }

    public function test_tramite_policy_does_not_infer_organizational_scope_from_role_name(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $tramite = new Tramite(['created_by' => $owner->id]);

        $this->assertFalse($other->can('view', $tramite));
    }
}
