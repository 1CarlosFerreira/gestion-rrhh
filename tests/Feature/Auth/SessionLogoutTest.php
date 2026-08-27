<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SessionLogoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_sees_name_and_logout_action(): void
    {
        $user = User::factory()->create(['name' => 'Usuario Ficticio Visible']);

        $this->actingAs($user)->get('/dashboard')
            ->assertOk()
            ->assertSee('Usuario Ficticio Visible')
            ->assertSee('Cerrar sesión')
            ->assertSee('action="'.route('logout').'"', false)
            ->assertSee('method="POST"', false);
    }

    public function test_guest_does_not_see_logout_action(): void
    {
        $this->get('/login')->assertOk()->assertDontSee('Cerrar sesión');
    }

    public function test_post_logout_invalidates_session_and_protected_page_redirects_to_login(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/dashboard')->assertOk();

        $this->post(route('logout'))->assertRedirect('/');

        $this->assertGuest();
        $this->get('/dashboard')->assertRedirect(route('login'));
    }

    public function test_administrator_can_logout(): void
    {
        $this->assertRoleCanLogout('Administrador');
    }

    public function test_service_manager_can_logout(): void
    {
        $this->assertRoleCanLogout('Jefe de Servicio');
    }

    public function test_people_management_can_logout(): void
    {
        $this->assertRoleCanLogout('Gestión de Personas');
    }

    public function test_get_logout_is_not_a_logout_mechanism(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/logout')->assertMethodNotAllowed();
        $this->assertAuthenticatedAs($user);
    }

    private function assertRoleCanLogout(string $role): void
    {
        $this->seed();
        $user = User::factory()->create();
        $user->assignRole($role);

        $this->actingAs($user)->get('/dashboard')->assertOk()->assertSee('Cerrar sesión');
        $this->post(route('logout'))->assertRedirect('/');
        $this->assertGuest();
    }
}
