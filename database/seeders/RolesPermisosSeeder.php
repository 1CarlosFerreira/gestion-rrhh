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
            'tramites.ver_propios', 'tramites.ver_unidad', 'tramites.ver_todos',
            'tramites.crear', 'tramites.transicionar',
            'tramites.adjuntos.cargar', 'tramites.adjuntos.descargar',
            'tramites.adjuntos.anular',
            'reemplazos.crear', 'reemplazos.revisar_personal', 'reemplazos.devolver',
            'horas_extra.crear', 'horas_extra.cargar_planilla',
            'horas_extra.revisar_planilla', 'horas_extra.registrar_horas',
            'documentos.generar', 'docdigital.registrar_envio',
            'docdigital.registrar_formalizacion', 'admin.usuarios',
            'admin.catalogos', 'admin.roles_permisos',
            'personas.ver', 'personas.gestionar', 'dotacion.ver',
            'usuarios.unidades.gestionar',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        Role::findOrCreate('Jefe de Servicio', 'web')->syncPermissions([
            'tramites.ver_propios', 'tramites.ver_unidad', 'tramites.crear',
            'tramites.transicionar', 'tramites.adjuntos.cargar',
            'tramites.adjuntos.descargar', 'reemplazos.crear',
            'horas_extra.crear', 'horas_extra.revisar_planilla',
            'documentos.generar',
            'personas.ver', 'dotacion.ver',
        ]);

        Role::findOrCreate('Gestión de Personas', 'web')->syncPermissions([
            'tramites.ver_todos', 'tramites.transicionar',
            'tramites.adjuntos.cargar', 'tramites.adjuntos.descargar',
            'tramites.adjuntos.anular',
            'reemplazos.revisar_personal', 'reemplazos.devolver',
            'horas_extra.cargar_planilla', 'horas_extra.registrar_horas',
            'docdigital.registrar_envio',
            'docdigital.registrar_formalizacion',
            'personas.ver', 'personas.gestionar', 'dotacion.ver',
        ]);

        Role::findOrCreate('Administrador', 'web')->syncPermissions($permissions);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
