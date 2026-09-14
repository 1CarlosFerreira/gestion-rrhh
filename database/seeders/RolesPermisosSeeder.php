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

        $permissions = [
            'admin.usuarios',
            'admin.roles_permisos',
            'tramites.ver_propios',
            'tramites.ver_todos',
            'tramites.crear',
            'tramites.adjuntos.cargar',
            'tramites.adjuntos.descargar',
            'tramites.adjuntos.anular',
            'reemplazos.crear',
            'reemplazos.revisar',
            'reemplazos.generar_documento',
            'reemplazos.formalizar',
            'estructura_organizacional.ver',
            'estructura_organizacional.gestionar',
            'tipos_unidad_organizacional.gestionar',
            'responsabilidades.ver',
            'responsabilidades.gestionar',
            'accesos_operativos.ver',
            'accesos_operativos.gestionar',
            'dotacion.ver',
            'dotacion.gestionar',
            'calidades_contractuales.ver',
            'calidades_contractuales.gestionar',
            'personas.ver',
            'personas.gestionar',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $rolePermissions = [
            'Administrador' => $permissions,
            'Gestión de Personas' => [
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
            ],
            'Solicitante' => [
                'dotacion.ver',
                'estructura_organizacional.ver',
                'personas.ver',
                'reemplazos.crear',
                'responsabilidades.ver',
                'tramites.adjuntos.cargar',
                'tramites.adjuntos.descargar',
                'tramites.crear',
                'tramites.ver_propios',
            ],
            'Funcionario' => [],
        ];

        foreach ($rolePermissions as $roleName => $assignedPermissions) {
            Role::findOrCreate($roleName, 'web')->syncPermissions($assignedPermissions);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
