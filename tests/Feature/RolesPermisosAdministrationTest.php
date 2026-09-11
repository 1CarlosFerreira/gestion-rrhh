<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesPermisosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RolesPermisosAdministrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_permission_controls_access_to_roles_and_permissions_administration(): void
    {
        $this->seed(RolesPermisosSeeder::class);
        $unauthorized = User::factory()->create(['active' => true]);
        $authorized = User::factory()->create(['active' => true]);
        $authorized->givePermissionTo('admin.roles_permisos');

        $this->actingAs($unauthorized)->get(route('admin.roles-permisos.index'))->assertForbidden();
        $this->actingAs($authorized)->get(route('admin.roles-permisos.index'))->assertOk()->assertSee('Roles y permisos');
    }

    public function test_authorized_user_can_create_role_with_grouped_permissions(): void
    {
        $this->seed(RolesPermisosSeeder::class);
        $actor = User::factory()->create(['active' => true]);
        $actor->givePermissionTo('admin.roles_permisos');

        $this->actingAs($actor)
            ->get(route('admin.roles-permisos.create'))
            ->assertOk()
            ->assertSee('Administración')
            ->assertSee('Dotacion')
            ->assertSee('admin.roles_permisos');

        $this->actingAs($actor)
            ->post(route('admin.roles-permisos.store'), [
                'name' => 'Auditoría',
                'permissions' => ['personas.ver', 'dotacion.ver'],
            ])
            ->assertRedirect(route('admin.roles-permisos.index'));

        $role = Role::findByName('Auditoría');
        $this->assertTrue($role->hasAllPermissions(['personas.ver', 'dotacion.ver']));
    }

    public function test_edit_role_syncs_permissions_instead_of_creating_parallel_assignments(): void
    {
        $this->seed(RolesPermisosSeeder::class);
        $permissionCount = Permission::query()->count();
        $actor = User::factory()->create(['active' => true]);
        $actor->givePermissionTo('admin.roles_permisos');
        $role = Role::create(['name' => 'Consulta', 'guard_name' => 'web']);
        $role->givePermissionTo(['personas.ver', 'dotacion.ver']);

        $this->actingAs($actor)
            ->put(route('admin.roles-permisos.update', $role), [
                'name' => 'Consulta general',
                'permissions' => ['tramites.ver_propios'],
            ])
            ->assertRedirect(route('admin.roles-permisos.index'));

        $role->refresh();
        $this->assertSame('Consulta general', $role->name);
        $this->assertSame(['tramites.ver_propios'], $role->permissions()->pluck('name')->all());
        $this->assertSame($permissionCount, Permission::query()->count());
        $this->assertSame(['admin.roles_permisos'], $actor->permissions()->pluck('name')->all());
    }
}
