<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesPermisosSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = ['admin.usuarios', 'admin.roles_permisos', 'tramites.ver_propios', 'tramites.ver_todos', 'tramites.crear', 'tramites.adjuntos.cargar', 'tramites.adjuntos.descargar', 'tramites.adjuntos.anular', 'reemplazos.crear', 'estructura_organizacional.ver', 'estructura_organizacional.gestionar', 'tipos_unidad_organizacional.gestionar', 'responsabilidades.ver', 'responsabilidades.gestionar', 'accesos_operativos.ver', 'accesos_operativos.gestionar', 'dotacion.ver', 'dotacion.gestionar', 'calidades_contractuales.ver', 'calidades_contractuales.gestionar'];
        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        Role::findOrCreate('Funcionario', 'web');
        Role::findOrCreate('Jefatura', 'web');
        Role::findOrCreate('Gestión de Personas', 'web');
        Role::findOrCreate('Administrador', 'web')->syncPermissions($permissions);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
