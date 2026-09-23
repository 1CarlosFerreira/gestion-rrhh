<?php

namespace Tests\Feature;

use App\Models\Persona;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PersonaRutValidationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->admin = User::factory()->create();
        $this->admin->assignRole('Administrador');
    }

    public function test_creates_persona_with_valid_formatted_rut_and_stores_canonical_value(): void
    {
        $this->actingAs($this->admin)->post(route('admin.personas.store'), $this->data('12.345.678-5'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('personas', ['rut' => '12345678-5']);
    }

    public function test_creates_persona_with_valid_unformatted_rut(): void
    {
        $this->actingAs($this->admin)->post(route('admin.personas.store'), $this->data('6532697-3'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('personas', ['rut' => '6532697-3']);
    }

    public function test_accepts_lowercase_k_and_normalizes_it(): void
    {
        $this->actingAs($this->admin)->post(route('admin.personas.store'), $this->data('5.000.001-k'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('personas', ['rut' => '5000001-K']);
    }

    public function test_rejects_invalid_rut(): void
    {
        $this->actingAs($this->admin)->post(route('admin.personas.store'), $this->data('12.345.678-9'))
            ->assertSessionHasErrors('rut');

        $this->assertDatabaseMissing('personas', ['rut' => '12345678-9']);
    }

    public function test_rejects_duplicate_rut_after_normalization(): void
    {
        Persona::query()->create($this->data('12345678-5'));

        $this->actingAs($this->admin)->post(route('admin.personas.store'), $this->data('12.345.678-5'))
            ->assertSessionHasErrors('rut');
    }

    public function test_edit_validates_and_normalizes_rut(): void
    {
        $persona = Persona::query()->create($this->data('6532697-3'));

        $this->actingAs($this->admin)->put(route('admin.personas.update', $persona), $this->data('12.345.678-5'))
            ->assertSessionHasNoErrors();

        $this->assertSame('12345678-5', $persona->fresh()->rut);
    }

    public function test_edit_without_associated_user_updates_persona_and_returns_to_its_record(): void
    {
        $persona = Persona::query()->create($this->data('6532697-3'));

        $this->actingAs($this->admin)
            ->put(route('admin.personas.update', $persona), [
                ...$this->data('12345678-5'),
                'nombres' => 'Nombre actualizado',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.personas.show', $persona));

        $this->assertSame('Nombre actualizado', $persona->fresh()->nombres);
        $this->assertNull($persona->fresh()->user);
    }

    public function test_edit_rejects_a_rut_already_used_by_another_persona(): void
    {
        $persona = Persona::query()->create($this->data('6532697-3'));
        Persona::query()->create([...$this->data('12345678-5'), 'nombres' => 'Otra']);

        $this->actingAs($this->admin)
            ->put(route('admin.personas.update', $persona), $this->data('12.345.678-5'))
            ->assertSessionHasErrors('rut');

        $this->assertSame('6532697-3', $persona->fresh()->rut);
    }

    public function test_user_sync_failure_rolls_back_the_persona_identity_update(): void
    {
        $persona = Persona::query()->create($this->data('6532697-3'));
        User::factory()->create([
            'persona_id' => $persona->id,
            'rut' => $persona->rut,
            'name' => $persona->nombre_completo,
        ]);
        User::factory()->create(['rut' => '12345678-5']);
        $this->withoutExceptionHandling();

        try {
            $this->actingAs($this->admin)
                ->put(route('admin.personas.update', $persona), [
                    ...$this->data('12345678-5'),
                    'nombres' => 'No debe persistir',
                ]);
            $this->fail('La sincronización debía fallar por el RUT duplicado en users.');
        } catch (QueryException) {
            $this->assertSame('6532697-3', $persona->fresh()->rut);
            $this->assertSame('Persona', $persona->fresh()->nombres);
            $this->assertSame('6532697-3', $persona->fresh()->user->rut);
            $this->assertSame('Persona Ficticia', $persona->fresh()->user->name);
        }
    }

    public function test_changing_persona_rut_updates_the_user_login_identity(): void
    {
        $persona = Persona::query()->create($this->data('6532697-3'));
        $user = User::factory()->create([
            'persona_id' => $persona->id,
            'rut' => $persona->rut,
            'name' => $persona->nombre_completo,
            'password' => 'password',
            'active' => true,
        ]);

        $this->actingAs($this->admin)
            ->put(route('admin.personas.update', $persona), $this->data('12.345.678-5'))
            ->assertSessionHasNoErrors();

        $this->post('/logout');
        $this->post('/login', ['identifier' => '6532697-3', 'password' => 'password'])
            ->assertSessionHasErrors('identifier');
        $this->post('/login', ['identifier' => '12.345.678-5', 'password' => 'password'])
            ->assertSessionHasNoErrors();

        $this->assertAuthenticatedAs($user);
        $this->assertSame('12345678-5', $persona->fresh()->rut);
        $this->assertSame('12345678-5', $user->fresh()->rut);
    }

    private function data(string $rut): array
    {
        return [
            'rut' => $rut,
            'nombres' => 'Persona',
            'apellido_paterno' => 'Ficticia',
            'apellido_materno' => null,
            'active' => true,
        ];
    }
}
