<?php

namespace Database\Seeders;

use App\Models\EstadoTramite;
use App\Models\TipoTramite;
use App\Models\TransicionEstado;
use App\Services\Reemplazos\AnulacionTransitoria;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use LogicException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class AnulacionesTransitoriasV3Seeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        try {
            DB::transaction(function (): void {
                foreach (['reemplazos.anular', 'reemplazos.anular_enviadas'] as $permiso) {
                    Permission::findOrCreate($permiso, 'web');
                }
                foreach (['Administrador', 'Gestión de Personas', 'Jefatura', 'Solicitante'] as $rol) {
                    Role::findOrCreate($rol, 'web')->givePermissionTo('reemplazos.anular');
                }
                Role::findOrCreate('Gestión de Personas', 'web')->givePermissionTo('reemplazos.anular_enviadas');
                $tipo = TipoTramite::query()->where('codigo', 'REEMPLAZO')->firstOrFail();
                $destino = EstadoTramite::query()->firstOrCreate(['tipo_tramite_id' => $tipo->id, 'codigo' => 'ANULADA'], ['nombre' => 'Anulada', 'orden' => 8, 'activo' => true]);
                foreach (AnulacionTransitoria::ESTADOS as $codigo) {
                    $origen = EstadoTramite::query()->where('tipo_tramite_id', $tipo->id)->where('codigo', $codigo)->firstOrFail();
                    $transicion = TransicionEstado::query()->firstOrCreate(['tipo_tramite_id' => $tipo->id, 'estado_origen_id' => $origen->id, 'codigo_accion' => 'ANULAR_SOLICITUD'], ['estado_destino_id' => $destino->id, 'permiso_requerido' => 'reemplazos.anular', 'requiere_observacion' => true, 'activo' => true]);
                    if ($transicion->estado_destino_id !== $destino->id
                        || $transicion->permiso_requerido !== 'reemplazos.anular'
                        || ! $transicion->requiere_observacion || ! $transicion->activo) {
                        throw new LogicException('Existe una transición ANULAR_SOLICITUD incompatible; revise el catálogo sin sobrescribirlo.');
                    }
                }
                if (! $destino->activo) {
                    throw new LogicException('El estado ANULADA existente está inactivo; revise el catálogo sin sobrescribirlo.');
                }
            });
        } finally {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }
}
