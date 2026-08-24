<?php

namespace Database\Seeders;

use App\Actions\Tramites\CrearTramite;
use App\Models\TipoTramite;
use App\Models\UnidadServicio;
use App\Models\User;
use Illuminate\Database\Seeder;

class TramitesSeeder extends Seeder
{
    public function __construct(private readonly CrearTramite $crearTramite) {}

    public function run(): void
    {
        $admin = User::query()->where('email', 'admin@example.test')->firstOrFail();
        $jefe = User::query()->where('email', 'jefatura@example.test')->firstOrFail();
        $reemplazo = TipoTramite::query()->where('codigo', 'REEMPLAZO')->firstOrFail();
        $horas = TipoTramite::query()->where('codigo', 'HORAS_EXTRAORDINARIAS')->firstOrFail();
        $unidadJefe = $jefe->unidadesHabilitadas()->firstOrFail();
        $farmacia = UnidadServicio::query()->where('nombre', 'U. de Farmacia')->firstOrFail();

        if (! $jefe->tramitesCreados()->exists()) {
            $this->crearTramite->execute($reemplazo, $unidadJefe, $jefe);
        }
        if (! $admin->tramitesCreados()->exists()) {
            $this->crearTramite->execute($horas, $farmacia, $admin);
        }
    }
}
