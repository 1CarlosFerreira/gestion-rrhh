<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesPermisosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoleConsolidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_defines_the_four_base_roles_and_their_permissions(): void
    {
        $this->seed(RolesPermisosSeeder::class);
        $this->seed(RolesPermisosSeeder::class);

        $this->assertSame(
            ['Administrador', 'Funcionario', 'Gestión de Personas', 'Solicitante'],
            Role::query()->orderBy('name')->pluck('name')->all()
        );
        $this->assertSame([], Role::findByName('Funcionario')->permissions()->pluck('name')->all());
        $this->assertEqualsCanonicalizing([
            'dotacion.ver',
            'estructura_organizacional.ver',
            'personas.ver',
            'reemplazos.crear',
            'responsabilidades.ver',
            'tramites.adjuntos.cargar',
            'tramites.adjuntos.descargar',
            'tramites.crear',
            'tramites.ver_propios',
        ], Role::findByName('Solicitante')->permissions()->pluck('name')->all());
        $this->assertEqualsCanonicalizing([
            'dotacion.gestionar',
            'dotacion.ver',
            'personas.gestionar',
            'personas.ver',
            'reemplazos.formalizar',
            'reemplazos.generar_documento',
            'reemplazos.revisar',
            'responsabilidades.ver',
            'tramites.adjuntos.anular',
            'tramites.adjuntos.cargar',
            'tramites.adjuntos.descargar',
            'tramites.ver_todos',
        ], Role::findByName('Gestión de Personas')->permissions()->pluck('name')->all());
        $this->assertCount(Permission::query()->count(), Role::findByName('Administrador')->permissions);
    }

    public function test_migration_consolidates_roles_preserving_jefatura_id_assignments_and_permissions(): void
    {
        Role::query()->where('name', 'Solicitante')->where('guard_name', 'web')->delete();

        foreach (['personas.ver', 'responsabilidades.ver', 'tramites.crear'] as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $jefatura = Role::create(['name' => 'Jefatura', 'guard_name' => 'web']);
        $jefatura->givePermissionTo('personas.ver');
        $jefaturaUser = User::factory()->create();
        $jefaturaUser->assignRole($jefatura);

        $solicitante = Role::create(['name' => 'Solicitante', 'guard_name' => 'web']);
        $solicitante->givePermissionTo('responsabilidades.ver');
        $solicitanteUser = User::factory()->create();
        $solicitanteUser->assignRole($solicitante);

        $administrativo = Role::create(['name' => 'Administrativo', 'guard_name' => 'web']);
        $administrativo->givePermissionTo('tramites.crear');
        $administrativoUser = User::factory()->create();
        $administrativoUser->assignRole([$administrativo, $jefatura]);

        $migration = require database_path('migrations/2026_09_14_120000_consolidate_requester_roles.php');
        $migration->up();
        $migration->up();

        $role = Role::findByName('Solicitante');

        $this->assertSame($jefatura->id, $role->id);
        $this->assertFalse(Role::query()->whereIn('name', ['Jefatura', 'Administrativo'])->exists());
        $this->assertEqualsCanonicalizing(
            ['personas.ver', 'responsabilidades.ver', 'tramites.crear'],
            $role->permissions()->pluck('name')->all()
        );
        $this->assertTrue($jefaturaUser->fresh()->hasRole($role));
        $this->assertTrue($solicitanteUser->fresh()->hasRole($role));
        $this->assertTrue($administrativoUser->fresh()->hasRole($role));
        $this->assertCount(1, $administrativoUser->fresh()->roles);
    }
}
