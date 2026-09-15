<?php

namespace App\Http\Controllers;

use App\Models\Persona;
use App\Models\Tramite;
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
        $misTramites = collect();
        $tramitesRequierenAtencion = collect();
        $resumenMisTramites = ['requieren_atencion' => 0, 'en_tramitacion' => 0, 'abiertos' => 0];
        if ($user->can('tramites.ver_propios')) {
            $propiosAbiertos = Tramite::query()
                ->where('created_by', $user->id)
                ->whereNull('finalized_at');
            $requierenAtencion = fn ($query) => $query->whereHas('estadoTramite', fn ($estado) => $estado->whereIn('codigo', ['BORRADOR', 'DEVUELTA_PARA_CORRECCION']));
            $resumenMisTramites['abiertos'] = (clone $propiosAbiertos)->count();
            $resumenMisTramites['requieren_atencion'] = (clone $propiosAbiertos)->tap($requierenAtencion)->count();
            $resumenMisTramites['en_tramitacion'] = $resumenMisTramites['abiertos'] - $resumenMisTramites['requieren_atencion'];
            $misTramites = (clone $propiosAbiertos)
                ->with(['tipoTramite', 'estadoTramite', 'unidadOrganizacional', 'reemplazo.funcionario'])
                ->latest('updated_at')
                ->limit(5)
                ->get();
            $tramitesRequierenAtencion = (clone $propiosAbiertos)
                ->tap($requierenAtencion)
                ->with(['tipoTramite', 'estadoTramite', 'unidadOrganizacional'])
                ->latest('updated_at')
                ->limit(5)
                ->get();
        }

        if (! $user->hasRole('Administrador')) {
            return view('dashboard', compact('misTramites', 'tramitesRequierenAtencion', 'resumenMisTramites') + ['esAdministrador' => false]);
        }

        return view('dashboard', [
            'esAdministrador' => true,
            'misTramites' => $misTramites,
            'tramitesRequierenAtencion' => $tramitesRequierenAtencion,
            'resumenMisTramites' => $resumenMisTramites,
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
