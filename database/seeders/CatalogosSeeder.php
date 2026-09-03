<?php

namespace Database\Seeders;

use App\Models\Estamento;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CatalogosSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Médico', 'Odontólogo', 'Bioquímico', 'Químico Farmacéutico', 'Profesional', 'Técnico', 'Administrativo', 'Auxiliar'] as $nombre) {
            Estamento::query()->updateOrCreate(
                ['codigo' => Str::upper(Str::slug($nombre, '_'))],
                ['nombre' => $nombre, 'activo' => true],
            );
        }
    }
}
