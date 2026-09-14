<?php

namespace App\Http\Controllers;

use App\Models\Persona;
use App\Models\UnidadOrganizacional;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();

        if (! $user->hasRole('Administrador')) {
            return view('dashboard', ['esAdministrador' => false]);
        }

        return view('dashboard', [
            'esAdministrador' => true,
            'resumen' => [
                'personas' => ['total' => Persona::query()->count(), 'activas' => Persona::query()->where('active', true)->count()],
                'usuarios' => ['total' => User::query()->count(), 'activos' => User::query()->where('active', true)->count()],
                'unidades' => ['total' => UnidadOrganizacional::query()->count(), 'activas' => UnidadOrganizacional::query()->where('activo', true)->count()],
                'roles' => ['total' => Role::query()->count()],
            ],
            'usuariosActivosSinRoles' => User::query()->where('active', true)->doesntHave('roles')->count(),
        ]);
    }
}
