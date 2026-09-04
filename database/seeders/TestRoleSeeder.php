<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class TestRoleSeeder extends Seeder
{
    public function run(): void
    {
        if (!Role::where('name', 'Administrador')->exists()) {
            Role::create(['name' => 'Administrador']);
        }
    }
}