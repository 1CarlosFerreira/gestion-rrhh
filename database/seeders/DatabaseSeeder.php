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
            CalidadesContractualesOficialesSeeder::class,
            TiposUnidadOrganizacionalSeeder::class,
            EstructuraOrganizacionalOficialSeeder::class,
            ReemplazosV2Seeder::class,
            TiposDocumentoSeeder::class,
            DocumentoPlantillaReemplazoV2Seeder::class,
            AdminUserSeeder::class,
        ]);
    }
}
