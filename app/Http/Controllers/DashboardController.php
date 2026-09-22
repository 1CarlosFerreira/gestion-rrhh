<?php

namespace App\Http\Controllers;

use App\Models\Persona;
use App\Models\Tramite;
use App\Models\UnidadOrganizacional;
use App\Models\User;
use App\Services\Accesos\AccesoOperativoService;
use App\Services\Reemplazos\AlcanceSolicitudReemplazoService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class DashboardController extends Controller
{
    public function __invoke(Request $request, AccesoOperativoService $accesos, AlcanceSolicitudReemplazoService $alcanceReemplazos): View
    {
        $user = $request->user();
        $esAdministrador = $user->hasRole('Administrador');
        $panelGestionPersonas = null;
        $misTramites = collect();
        $tramitesRequierenAtencion = collect();
        $resumenMisTramites = ['requieren_atencion' => 0, 'en_tramitacion' => 0, 'abiertos' => 0];
        if ($user->active && $user->can('tramites.ver_unidades')) {
            $unidadesAutorizadas = $alcanceReemplazos->unidadesAutorizadas($user, today())->pluck('id');
            $tramitesAbiertos = Tramite::query()
                ->whereIn('unidad_organizacional_id', $unidadesAutorizadas)
                ->whereNull('finalized_at');
            $requierenAtencion = fn ($query) => $query
                ->whereHas('estadoTramite', fn ($estado) => $estado->whereIn('codigo', ['BORRADOR', 'DEVUELTA_PARA_CORRECCION']))
                ->when(! $user->can('reemplazos.crear'), fn ($query) => $query->whereRaw('1 = 0'));
            $resumenMisTramites['abiertos'] = (clone $tramitesAbiertos)->count();
            $resumenMisTramites['requieren_atencion'] = (clone $tramitesAbiertos)->tap($requierenAtencion)->count();
            $resumenMisTramites['en_tramitacion'] = $resumenMisTramites['abiertos'] - $resumenMisTramites['requieren_atencion'];
            $misTramites = (clone $tramitesAbiertos)
                ->with(['tipoTramite', 'estadoTramite', 'unidadOrganizacional', 'creador', 'reemplazo.funcionario'])
                ->latest('updated_at')
                ->limit(5)
                ->get();
            $tramitesRequierenAtencion = (clone $tramitesAbiertos)
                ->tap($requierenAtencion)
                ->with(['tipoTramite', 'estadoTramite', 'unidadOrganizacional', 'creador'])
                ->latest('updated_at')
                ->limit(5)
                ->get();
        }

        if (! $esAdministrador && $user->can('reemplazos.revisar')) {
            $unidadesAccesibles = $accesos->unidadesAccesibles($user, today())->pluck('id');
            $consultaReemplazos = Tramite::query()
                ->whereHas('tipoTramite', fn ($query) => $query->where('codigo', 'REEMPLAZO'))
                ->when(! $user->can('tramites.ver_todos'), fn ($query) => $query->whereIn('unidad_organizacional_id', $unidadesAccesibles));
            $estadosIndicadores = [
                'pendientes' => 'ENVIADA_GESTION_PERSONAS',
                'en_revision' => 'EN_REVISION',
                'para_documento' => 'LISTA_GENERAR_DOCUMENTO',
            ];
            $estadosAtencion = ['ENVIADA_GESTION_PERSONAS', 'EN_REVISION'];
            if ($user->can('reemplazos.generar_documento')) {
                $estadosAtencion[] = 'LISTA_GENERAR_DOCUMENTO';
            }

            $panelGestionPersonas = [
                'reemplazos' => [
                    'indicadores' => collect($estadosIndicadores)->map(fn (string $estado, string $clave): int => $clave === 'para_documento' && ! $user->can('reemplazos.generar_documento')
                        ? 0
                        : (clone $consultaReemplazos)->whereHas('estadoTramite', fn ($query) => $query->where('codigo', $estado))->count()),
                    'requieren_atencion' => (clone $consultaReemplazos)
                        ->whereHas('estadoTramite', fn ($query) => $query->whereIn('codigo', $estadosAtencion))
                        ->with(['estadoTramite', 'unidadOrganizacional', 'reemplazo.funcionario', 'reemplazo.reemplazante'])
                        ->orderBy('submitted_at')
                        ->orderBy('created_at')
                        ->limit(5)
                        ->get(),
                ],
            ];
        }

        if (! $esAdministrador) {
            return view('dashboard', compact('misTramites', 'tramitesRequierenAtencion', 'resumenMisTramites', 'panelGestionPersonas') + ['esAdministrador' => false, 'esGestionPersonas' => $panelGestionPersonas !== null]);
        }

        return view('dashboard', [
            'esAdministrador' => true,
            'esGestionPersonas' => false,
            'panelGestionPersonas' => null,
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
