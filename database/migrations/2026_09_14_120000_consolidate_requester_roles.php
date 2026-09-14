<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        DB::transaction(function (): void {
            $jefatura = Role::query()
                ->where('name', 'Jefatura')
                ->where('guard_name', 'web')
                ->first();
            $solicitante = Role::query()
                ->where('name', 'Solicitante')
                ->where('guard_name', 'web')
                ->first();

            if ($jefatura) {
                if ($solicitante && ! $solicitante->is($jefatura)) {
                    $this->mergeRoleInto($solicitante, $jefatura);
                    $solicitante->delete();
                }

                $jefatura->update(['name' => 'Solicitante']);
                $solicitante = $jefatura;
            } elseif (! $solicitante) {
                $solicitante = Role::create([
                    'name' => 'Solicitante',
                    'guard_name' => 'web',
                ]);
            }

            $administrativo = Role::query()
                ->where('name', 'Administrativo')
                ->where('guard_name', 'web')
                ->first();

            if ($administrativo && ! $administrativo->is($solicitante)) {
                $this->mergeRoleInto($administrativo, $solicitante);
                $administrativo->delete();
            }
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        throw new LogicException('La consolidación de roles no puede revertirse sin perder la procedencia de usuarios y permisos.');
    }

    private function mergeRoleInto(Role $source, Role $target): void
    {
        $target->permissions()->syncWithoutDetaching(
            $source->permissions()->pluck('permissions.id')->all()
        );

        $tableNames = config('permission.table_names');
        $columnNames = config('permission.column_names');
        $roleKey = $columnNames['role_pivot_key'] ?? 'role_id';
        $modelKey = $columnNames['model_morph_key'] ?? 'model_id';

        DB::table($tableNames['model_has_roles'])
            ->where($roleKey, $source->getKey())
            ->orderBy($modelKey)
            ->each(function (object $assignment) use ($tableNames, $roleKey, $target): void {
                $values = (array) $assignment;
                $values[$roleKey] = $target->getKey();

                DB::table($tableNames['model_has_roles'])->insertOrIgnore($values);
            });
    }
};
