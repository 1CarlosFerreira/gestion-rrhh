<?php

namespace Database\Seeders;

use App\Models\CalidadContractual;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CalidadesContractualesOficialesSeeder extends Seeder
{
    public function run(): void
    {
        $calidades = [
            'COMPRA_SERVICIO' => 'Compra De Servicio',
            'COMISION_SERVICIO' => 'Comision De Servicio',
            'CONTRATA' => 'Contrata',
            'HONORARIO' => 'Honorario',
            'REEMPLAZO' => 'Reemplazo',
            'TITULAR' => 'Titular',
            'ALUMNO' => 'Alumno',
            'TRABAJADOR_EXTERNO' => 'Trabajador Externo',
        ];

        DB::transaction(function () use ($calidades): void {
            foreach ($calidades as $codigo => $nombre) {
                $calidad = CalidadContractual::query()
                    ->whereRaw('LOWER(codigo) = ?', [mb_strtolower($codigo)])
                    ->orWhereRaw('LOWER(nombre) = ?', [mb_strtolower($nombre)])
                    ->first();

                if ($calidad === null) {
                    $calidad = new CalidadContractual;
                }

                $calidad->fill([
                    'codigo' => $codigo,
                    'nombre' => $nombre,
                    'activo' => true,
                    'orden' => array_search($codigo, array_keys($calidades), true) + 1,
                ])->save();
            }
        });
    }
}
