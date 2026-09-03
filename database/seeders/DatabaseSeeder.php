<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolesPermisosSeeder::class,
            CatalogosSeeder::class,
            TiposUnidadOrganizacionalSeeder::class,
            EstructuraOrganizacionalOficialSeeder::class,
            TiposDocumentoSeeder::class,
            AdminUserSeeder::class,
        ]);
    }
}
