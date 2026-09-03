<?php

namespace Tests\Feature\Auth;

use App\Models\Persona;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered_and_public_registration_is_absent(): void
    {
        $this->get('/login')->assertOk()->assertSee('RUT o correo electrónico');
        $this->get('/register')->assertNotFound();
    }

    public function test_user_can_authenticate_with_formatted_rut(): void
    {
        $user = $this->userWithRut('12.345.678-5');

        $this->post('/login', ['identifier' => '12.345.678-5', 'password' => 'password'])
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($user);
    }

    public function test_user_can_authenticate_with_unformatted_rut(): void
    {
        $user = $this->userWithRut('12.345.678-5');

        $this->post('/login', ['identifier' => '123456785', 'password' => 'password']);

        $this->assertAuthenticatedAs($user);
    }

    public function test_user_can_authenticate_with_email(): void
    {
        $user = User::factory()->create(['email' => 'persona@example.test']);

        $this->post('/login', ['identifier' => 'PERSONA@example.test', 'password' => 'password']);

        $this->assertAuthenticatedAs($user);
    }

    public function test_wrong_password_is_rejected_without_updating_last_login(): void
    {
        $user = $this->userWithRut('12.345.678-5');

        $this->post('/login', ['identifier' => $user->rut, 'password' => 'incorrecta'])
            ->assertSessionHasErrors('identifier');

        $this->assertGuest();
        $this->assertNull($user->fresh()->last_login_at);
    }

    public function test_inactive_user_cannot_authenticate(): void
    {
        $user = User::factory()->create(['active' => false]);

        $this->post('/login', ['identifier' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors('identifier');

        $this->assertGuest();
    }

    public function test_unknown_rut_and_email_use_the_same_generic_error(): void
    {
        foreach (['99.999.999-9', 'nadie@example.test'] as $identifier) {
            $this->post('/login', ['identifier' => $identifier, 'password' => 'password'])
                ->assertSessionHasErrors(['identifier' => trans('auth.failed')]);
        }

        $this->assertGuest();
    }

    public function test_last_login_is_updated_only_after_successful_authentication(): void
    {
        $user = User::factory()->create(['last_login_at' => null]);

        $this->post('/login', ['identifier' => $user->email, 'password' => 'password']);

        $this->assertNotNull($user->fresh()->last_login_at);
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/logout')->assertRedirect('/');

        $this->assertGuest();
    }

    private function userWithRut(string $rut): User
    {
        $persona = Persona::query()->create(['rut' => $rut, 'nombres' => 'Persona', 'apellido_paterno' => 'Ficticia']);

        return User::factory()->create(['rut' => $rut, 'persona_id' => $persona->id]);
    }
}
