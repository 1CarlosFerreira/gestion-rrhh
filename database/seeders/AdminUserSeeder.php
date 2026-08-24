<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->updateOrCreate(
            ['email' => 'admin@example.test'],
            [
                'name' => 'Administrador de Desarrollo',
                'password' => Hash::make('password'),
                'active' => true,
                'email_verified_at' => now(),
            ],
        );

        $admin->syncRoles('Administrador');
    }
}
