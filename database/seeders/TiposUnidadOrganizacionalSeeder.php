<?php

namespace Database\Seeders;

use App\Models\TipoUnidadOrganizacional;
use Illuminate\Database\Seeder;

class TiposUnidadOrganizacionalSeeder extends Seeder
{
    public function run(): void
    {
        $tipos = ['DIRECCION' => 'Dirección', 'SUBDIRECCION' => 'Subdirección', 'DEPARTAMENTO' => 'Departamento', 'AREA' => 'Área', 'UNIDAD' => 'Unidad', 'SERVICIO' => 'Servicio', 'OFICINA' => 'Oficina', 'OTRO' => 'Otro'];

        foreach ($tipos as $codigo => $nombre) {
            TipoUnidadOrganizacional::query()->updateOrCreate(
                ['codigo' => $codigo],
                ['nombre' => $nombre, 'activo' => true, 'orden' => array_search($codigo, array_keys($tipos), true) + 1],
            );
        }
    }
}
