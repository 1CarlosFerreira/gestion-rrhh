<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateUserRolesRequest;
use App\Http\Requests\StoreUserUnidadRequest;
use App\Models\UnidadServicio;
use App\Models\User;
use App\Models\UserUnidad;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index(): View
    {
        return view('admin.users.index', [
            'users' => User::query()->with(['roles', 'asignacionesUnidad.unidad'])->orderBy('name')->get(),
            'roles' => Role::query()->orderBy('name')->get(),
            'unidades' => UnidadServicio::query()->where('activo', true)->orderBy('nombre')->get(),
        ]);
    }

    public function updateRoles(UpdateUserRolesRequest $request, User $user): RedirectResponse
    {
        $validated = $request->validated();

        $user->syncRoles($validated['roles'] ?? []);

        return back()->with('status', 'Roles actualizados.');
    }

    public function toggleActive(User $user): RedirectResponse
    {
        abort_if($user->is(auth()->user()), 422, 'No puedes desactivar tu propia cuenta.');
        $user->update(['active' => ! $user->active]);

        return back()->with('status', 'Estado del usuario actualizado.');
    }

    public function storeUnidad(StoreUserUnidadRequest $request, User $user): RedirectResponse
    {
        UserUnidad::query()->updateOrCreate(
            ['user_id' => $user->id, 'unidad_servicio_id' => $request->integer('unidad_servicio_id')],
            [...$request->safe()->only(['valid_from', 'valid_to']), 'active' => true],
        );

        return back()->with('status', 'Unidad habilitada para el usuario.');
    }

    public function toggleUnidad(User $user, UserUnidad $asignacion): RedirectResponse
    {
        abort_unless($asignacion->user_id === $user->id, 404);
        $asignacion->update(['active' => ! $asignacion->active]);

        return back()->with('status', 'Asignación de unidad actualizada.');
    }
}
