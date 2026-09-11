<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreRoleRequest;
use App\Http\Requests\Admin\UpdateRoleRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionController extends Controller
{
    public function index(): View
    {
        return view('admin.roles-permissions.index', [
            'roles' => Role::query()->where('guard_name', 'web')->withCount(['permissions', 'users'])->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return $this->form();
    }

    public function store(StoreRoleRequest $request): RedirectResponse
    {
        $role = Role::create(['name' => $request->validated('name'), 'guard_name' => 'web']);
        $role->syncPermissions($request->validated('permissions', []));

        return redirect()->route('admin.roles-permisos.index')->with('status', 'Rol creado.');
    }

    public function edit(Role $role): View
    {
        abort_unless($role->guard_name === 'web', 404);

        return $this->form($role);
    }

    public function update(UpdateRoleRequest $request, Role $role): RedirectResponse
    {
        abort_unless($role->guard_name === 'web', 404);
        $role->update(['name' => $request->validated('name')]);
        $role->syncPermissions($request->validated('permissions', []));

        return redirect()->route('admin.roles-permisos.index')->with('status', 'Rol actualizado.');
    }

    private function form(?Role $role = null): View
    {
        $role?->load('permissions');

        return view('admin.roles-permissions.form', [
            'role' => $role,
            'permisosAgrupados' => $this->permisosAgrupados(),
        ]);
    }

    private function permisosAgrupados(): Collection
    {
        return Permission::query()->where('guard_name', 'web')->orderBy('name')->get()
            ->groupBy(fn (Permission $permission): string => str($permission->name)->before('.')->toString())
            ->map(fn (Collection $permissions, string $modulo): array => [
                'nombre' => str($modulo)->replace('_', ' ')->title()->toString(),
                'permisos' => $permissions,
            ]);
    }
}
