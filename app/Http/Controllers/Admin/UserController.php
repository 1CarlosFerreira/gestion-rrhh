<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ResetUserPasswordRequest;
use App\Http\Requests\Admin\StoreUserForPersonaRequest;
use App\Http\Requests\Admin\UpdateUserEmailRequest;
use App\Http\Requests\Admin\UpdateUserRolesRequest;
use App\Models\Persona;
use App\Models\UnidadResponsable;
use App\Models\User;
use App\Services\Alcances\AlcanceFuncionalUnidadResolver;
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

    public function createForPersona(Request $request, Persona $persona): View
    {
        if ($request->boolean('continuar_perfil')) {
            abort_unless($request->user()->hasRole('Administrador'), 403);
        }

        $this->ensurePersonaHasNoUser($persona);

        return view('admin.users.create-for-persona', [
            'persona' => $persona,
            'continuarPerfil' => $request->boolean('continuar_perfil'),
        ]);
    }

    public function storeForPersona(StoreUserForPersonaRequest $request, Persona $persona): RedirectResponse
    {
        if ($request->boolean('continuar_perfil')) {
            abort_unless($request->user()->hasRole('Administrador'), 403);
        }

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

        if ($request->boolean('continuar_perfil')) {
            return redirect()->route('admin.usuarios.perfil-acceso.edit', ['user' => $user, 'creado' => 1]);
        }

        return redirect(route('admin.usuarios.index', ['user_id' => $user->id]).'#usuario-'.$user->id)
            ->with('status', 'Usuario creado. Ahora puede asignar roles o configurar accesos operativos.');
    }

    public function editAccessProfile(Request $request, User $user, AlcanceFuncionalUnidadResolver $alcance): View
    {
        abort_unless($request->user()->hasRole('Administrador'), 403);
        abort_if($user->persona_id === null, 404);

        $user->load(['persona', 'roles', 'accesosOperativos' => fn ($query) => $query->with('unidad')->vigentesEn(today())]);
        $unidadesConAlcance = $alcance->unidadesAutorizadas($user, today())->keyBy('id');
        $responsabilidades = $user->persona->responsabilidades()
            ->with('unidad')
            ->vigentesEn(today())
            ->where('puede_aprobar', true)
            ->get()
            ->filter(fn (UnidadResponsable $responsabilidad): bool => $unidadesConAlcance->has($responsabilidad->unidad_organizacional_id));

        return view('admin.users.access-profile', [
            'user' => $user,
            'roles' => Role::query()->orderBy('name')->get(),
            'responsabilidades' => $responsabilidades,
            'accesosOperativos' => $user->accesosOperativos,
            'usuarioCreado' => $request->boolean('creado'),
        ]);
    }

    public function updateRoles(UpdateUserRolesRequest $request, User $user): RedirectResponse
    {
        if ($request->boolean('finalizar_perfil')) {
            abort_unless($request->user()->hasRole('Administrador'), 403);
            abort_if($user->persona_id === null, 404);
        }

        $user->syncRoles($request->validated('roles', []));

        if ($request->boolean('finalizar_perfil')) {
            return redirect()->route('admin.personas.show', $user->persona)
                ->with('status', 'Roles actualizados. Configuración de acceso finalizada.');
        }

        return back()->with('status', 'Roles actualizados.');
    }

    public function updateEmail(UpdateUserEmailRequest $request, User $user): RedirectResponse
    {
        $user->update(['email' => $request->validated('email')]);

        return back()->with('status', 'Correo de acceso actualizado correctamente.');
    }

    public function resetPassword(ResetUserPasswordRequest $request, User $user): RedirectResponse
    {
        $user->update(['password' => $request->validated('password')]);

        return back()->with('status', 'Contraseña actualizada correctamente.');
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
