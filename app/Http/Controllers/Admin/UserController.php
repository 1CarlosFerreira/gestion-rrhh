<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserForPersonaRequest;
use App\Http\Requests\Admin\UpdateUserRolesRequest;
use App\Models\Persona;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim($request->string('buscar')->toString());

        return view('admin.users.index', [
            'users' => User::query()
                ->with(['persona', 'roles'])
                ->when($request->integer('user_id'), fn ($query, int $userId) => $query->whereKey($userId))
                ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search): void {
                    $like = '%'.$search.'%';

                    $query->where('name', 'like', $like)
                        ->orWhere('rut', 'like', $like)
                        ->orWhere('email', 'like', $like);
                }))
                ->when($request->string('estado')->toString() === 'activo', fn ($query) => $query->where('active', true))
                ->when($request->string('estado')->toString() === 'inactivo', fn ($query) => $query->where('active', false))
                ->when($request->filled('rol'), fn ($query) => $query->whereHas('roles', fn ($roles) => $roles->where('name', $request->string('rol')->toString())))
                ->orderBy('name')
                ->paginate(25)
                ->withQueryString(),
            'roles' => Role::query()->withCount('permissions')->orderBy('name')->get(),
        ]);
    }

    public function createForPersona(Persona $persona): View
    {
        $this->ensurePersonaHasNoUser($persona);

        return view('admin.users.create-for-persona', compact('persona'));
    }

    public function storeForPersona(StoreUserForPersonaRequest $request, Persona $persona): RedirectResponse
    {
        $user = DB::transaction(function () use ($request, $persona): User {
            $persona = Persona::query()->lockForUpdate()->findOrFail($persona->id);
            $this->ensurePersonaHasNoUser($persona);

            return User::query()->create([
                'persona_id' => $persona->id,
                'name' => $persona->nombre_completo,
                'rut' => $persona->rut,
                'email' => $request->validated('email'),
                'password' => $request->validated('password'),
                'active' => true,
            ]);
        });

        return redirect(route('admin.usuarios.index', ['user_id' => $user->id]).'#usuario-'.$user->id)
            ->with('status', 'Usuario creado. Ahora puede asignar roles o configurar accesos operativos.');
    }

    public function updateRoles(UpdateUserRolesRequest $request, User $user): RedirectResponse
    {
        $user->syncRoles($request->validated('roles', []));

        return back()->with('status', 'Roles actualizados.');
    }

    public function toggleActive(User $user): RedirectResponse
    {
        abort_if($user->is(auth()->user()), 422, 'No puedes desactivar tu propia cuenta.');
        $user->update(['active' => ! $user->active]);

        return back()->with('status', 'Estado del usuario actualizado.');
    }

    private function ensurePersonaHasNoUser(Persona $persona): void
    {
        if ($persona->user()->exists()) {
            throw ValidationException::withMessages([
                'persona' => 'Esta persona ya tiene una cuenta de usuario asociada.',
            ]);
        }
    }
}
