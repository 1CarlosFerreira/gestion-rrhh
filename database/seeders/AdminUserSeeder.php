<?php

namespace Database\Seeders;

use App\Models\Persona;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $persona = Persona::query()->updateOrCreate(
            ['rut' => '11111111-1'],
            ['nombres' => 'Administración', 'apellido_paterno' => 'Ficticia', 'active' => true],
        );

        $admin = User::query()->updateOrCreate(
            ['email' => 'admin@example.test'],
            [
                'persona_id' => $persona->id,
                'name' => 'Administrador de Desarrollo',
                'rut' => $persona->rut,
                'password' => Hash::make('password'),
                'active' => true,
                'email_verified_at' => now(),
            ],
        );

        $admin->syncRoles('Administrador');
    }
}
