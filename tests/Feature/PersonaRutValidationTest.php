<?php

namespace Tests\Feature;

use App\Models\Persona;
use App\Models\User;
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
