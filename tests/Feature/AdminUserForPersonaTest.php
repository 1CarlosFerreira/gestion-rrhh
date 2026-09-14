<?php

namespace Tests\Feature;

use App\Models\Persona;
use App\Models\User;
use Database\Seeders\RolesPermisosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminUserForPersonaTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Persona $persona;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesPermisosSeeder::class);
        $this->admin = User::factory()->create(['active' => true]);
        $this->admin->assignRole('Administrador');
        $this->persona = Persona::query()->create([
            'rut' => '12345678-9',
            'nombres' => 'Ana María',
            'apellido_paterno' => 'Prueba',
            'apellido_materno' => 'Ficticia',
        ]);
    }

    public function test_admin_can_create_active_user_for_existing_persona_without_roles_or_operational_access(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.usuarios.create-for-persona', $this->persona))
            ->assertOk()
            ->assertSee('Ana María Prueba Ficticia')
            ->assertSee('12345678-9');

        $response = $this->actingAs($this->admin)->post(route('admin.usuarios.store-for-persona', $this->persona), [
            'email' => 'ANA.PRUEBA@EXAMPLE.TEST',
            'password' => 'password-segura',
            'password_confirmation' => 'password-segura',
        ]);

        $user = User::query()->whereBelongsTo($this->persona)->sole();

        $response->assertRedirect(route('admin.usuarios.index', ['user_id' => $user->id]).'#usuario-'.$user->id);
        $this->assertSame('Ana María Prueba Ficticia', $user->name);
        $this->assertSame('12345678-9', $user->rut);
        $this->assertSame('ana.prueba@example.test', $user->email);
        $this->assertTrue($user->active);
        $this->assertTrue(Hash::check('password-segura', $user->password));
        $this->assertCount(0, $user->roles);
        $this->assertDatabaseCount('user_unidad_accesos', 0);
    }

    public function test_cannot_create_second_user_for_same_persona(): void
    {
        User::factory()->create([
            'persona_id' => $this->persona->id,
            'rut' => $this->persona->rut,
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.usuarios.create-for-persona', $this->persona))
            ->assertSessionHasErrors([
                'persona' => 'Esta persona ya tiene una cuenta de usuario asociada.',
            ]);

        $this->actingAs($this->admin)
            ->post(route('admin.usuarios.store-for-persona', $this->persona), [
                'email' => 'otra@example.test',
                'password' => 'password-segura',
                'password_confirmation' => 'password-segura',
            ])
            ->assertSessionHasErrors([
                'persona' => 'Esta persona ya tiene una cuenta de usuario asociada.',
            ]);

        $this->assertDatabaseCount('users', 2);
    }

    public function test_user_creation_requires_admin_users_permission_and_valid_credentials(): void
    {
        $unauthorized = User::factory()->create(['active' => true]);

        $this->actingAs($unauthorized)
            ->get(route('admin.usuarios.create-for-persona', $this->persona))
            ->assertForbidden();

        $this->actingAs($this->admin)
            ->post(route('admin.usuarios.store-for-persona', $this->persona), [
                'email' => 'correo-invalido',
                'password' => 'corta',
                'password_confirmation' => 'distinta',
            ])
            ->assertSessionHasErrors(['email', 'password']);

        $this->assertNull($this->persona->fresh()->user);
    }

    public function test_persona_card_shows_creation_or_existing_account_actions(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.personas.show', $this->persona))
            ->assertOk()
            ->assertSee('Acceso al sistema')
            ->assertSee('Sin cuenta de usuario')
            ->assertSee('Crear acceso al sistema');

        $user = User::factory()->create([
            'persona_id' => $this->persona->id,
            'rut' => $this->persona->rut,
            'email' => 'persona@example.test',
            'active' => false,
        ]);
        $user->assignRole('Solicitante');

        $this->actingAs($this->admin)
            ->get(route('admin.personas.show', $this->persona))
            ->assertOk()
            ->assertSee('persona@example.test')
            ->assertSee('Inactivo')
            ->assertSee('Solicitante')
            ->assertSee('Administrar roles')
            ->assertSee('Accesos operativos');
    }

    public function test_user_list_is_paginated_and_can_be_filtered_by_search_state_and_role(): void
    {
        User::factory()->count(25)->create();
        $target = User::factory()->create([
            'name' => 'Usuario Objetivo',
            'rut' => '98765432-1',
            'email' => 'objetivo@example.test',
            'active' => false,
        ]);
        $target->assignRole('Solicitante');

        $this->actingAs($this->admin)
            ->get(route('admin.usuarios.index'))
            ->assertOk()
            ->assertViewHas('users', fn (LengthAwarePaginator $users): bool => $users->perPage() === 25 && $users->total() === 27)
            ->assertSee('Gestionar')
            ->assertSee('Sin roles');

        foreach (['Usuario Objetivo', '98765432-1', 'objetivo@example.test'] as $search) {
            $this->actingAs($this->admin)
                ->get(route('admin.usuarios.index', ['buscar' => $search]))
                ->assertOk()
                ->assertSee('Usuario Objetivo');
        }

        $this->actingAs($this->admin)
            ->get(route('admin.usuarios.index', ['estado' => 'inactivo', 'rol' => 'Solicitante']))
            ->assertOk()
            ->assertSee('Usuario Objetivo')
            ->assertSee('Guardar roles')
            ->assertSee('Accesos operativos')
            ->assertSee('Activar usuario');
    }
}
