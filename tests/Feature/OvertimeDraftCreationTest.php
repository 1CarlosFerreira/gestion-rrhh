<?php

namespace Tests\Feature;

use App\Actions\HorasExtraordinarias\CrearHorasExtraordinarias;
use App\Models\Persona;
use App\Models\PersonaUnidadVinculo;
use App\Models\Tramite;
use App\Models\UnidadServicio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OvertimeDraftCreationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_form_only_shows_active_current_people_from_selected_unit(): void
    {
        [$valid, $other, $inactive] = $this->peopleFixtures();

        $response = $this->actingAs($this->jefe())->get(route('horas-extra.create', ['unidad_servicio_id' => $this->unit()->id]));

        $response->assertOk()
            ->assertSee('Funcionarios de '.$this->unit()->nombre)
            ->assertSee($valid->nombre_completo)
            ->assertDontSee($other->nombre_completo)
            ->assertDontSee($inactive->nombre_completo);
    }

    public function test_manual_request_cannot_add_person_from_another_unit(): void
    {
        [, $other] = $this->peopleFixtures();
        $before = Tramite::query()->count();

        $this->actingAs($this->jefe())->from(route('horas-extra.create'))->post(route('horas-extra.store'), [
            'unidad_servicio_id' => $this->unit()->id, 'year' => 2026, 'month' => 8, 'persona_ids' => [$other->id],
        ])->assertRedirect(route('horas-extra.create'))->assertSessionHasErrors('persona_ids');

        $this->assertDatabaseCount('tramites', $before);
    }

    public function test_action_also_rejects_person_without_current_active_unit_link(): void
    {
        [, $other] = $this->peopleFixtures();

        $this->expectException(ValidationException::class);
        app(CrearHorasExtraordinarias::class)->execute($this->unit(), 2026, 8, [$other->id], $this->jefe());
    }

    public function test_valid_post_creates_root_detail_participant_history_and_redirects(): void
    {
        [$valid] = $this->peopleFixtures();

        $response = $this->actingAs($this->jefe())->post(route('horas-extra.store'), [
            'unidad_servicio_id' => $this->unit()->id, 'year' => 2026, 'month' => 8, 'persona_ids' => [$valid->id],
        ]);

        $tramite = Tramite::query()->whereHas('tipoTramite', fn ($query) => $query->where('codigo', 'HORAS_EXTRAORDINARIAS'))->latest('id')->firstOrFail();
        $response->assertRedirect(route('horas-extra.edit', $tramite))->assertSessionHas('status', 'Borrador creado correctamente.');
        $this->assertSame('BORRADOR', $tramite->estadoTramite->codigo);
        $this->assertNotNull($tramite->horasExtra);
        $this->assertDatabaseHas('horas_extra_funcionarios', ['tramite_horas_extra_id' => $tramite->horasExtra->id, 'persona_id' => $valid->id]);
        $this->assertDatabaseHas('tramite_historial', ['tramite_id' => $tramite->id, 'action_code' => 'TRAMITE_CREADO']);
        $this->assertDatabaseHas('tramite_historial', ['tramite_id' => $tramite->id, 'action_code' => 'HORAS_EXTRA_FUNCIONARIO_AGREGADO']);
    }

    public function test_validation_error_is_visible_and_preserves_entered_values(): void
    {
        $this->peopleFixtures();

        $response = $this->actingAs($this->jefe())->from(route('horas-extra.create'))->post(route('horas-extra.store'), [
            'unidad_servicio_id' => $this->unit()->id, 'year' => 1999, 'month' => 8, 'persona_ids' => [],
        ]);

        $response->assertSessionHasErrors(['year', 'persona_ids']);
        $this->get(route('horas-extra.create'))->assertOk()
            ->assertSee('No fue posible guardar el borrador')
            ->assertSee('value="1999"', false)
            ->assertSee('value="'.$this->unit()->id.'" selected', false);
    }

    public function test_changing_unit_does_not_keep_invalid_participants(): void
    {
        [$first, $second] = $this->peopleFixtures();
        $secondUnit = $this->otherUnit();
        $this->jefe()->asignacionesUnidad()->updateOrCreate(['unidad_servicio_id' => $secondUnit->id], ['active' => true]);

        $this->actingAs($this->jefe())->get(route('horas-extra.create', ['unidad_servicio_id' => $secondUnit->id]))
            ->assertOk()
            ->assertSee($second->nombre_completo)
            ->assertDontSee($first->nombre_completo)
            ->assertDontSee('value="'.$first->id.'" checked', false);
    }

    private function peopleFixtures(): array
    {
        $valid = Persona::query()->create(['rut' => '60000001-0', 'nombres' => 'Válida Unidad', 'apellido_paterno' => 'Ficticia', 'active' => true]);
        $other = Persona::query()->create(['rut' => '60000002-0', 'nombres' => 'Otra Unidad', 'apellido_paterno' => 'Ficticia', 'active' => true]);
        $inactive = Persona::query()->create(['rut' => '60000003-0', 'nombres' => 'Vínculo Inactivo', 'apellido_paterno' => 'Ficticia', 'active' => true]);
        PersonaUnidadVinculo::query()->create(['persona_id' => $valid->id, 'unidad_servicio_id' => $this->unit()->id, 'status' => 'ACTIVO', 'start_date' => '2026-01-01']);
        PersonaUnidadVinculo::query()->create(['persona_id' => $other->id, 'unidad_servicio_id' => $this->otherUnit()->id, 'status' => 'ACTIVO', 'start_date' => '2026-01-01']);
        PersonaUnidadVinculo::query()->create(['persona_id' => $inactive->id, 'unidad_servicio_id' => $this->unit()->id, 'status' => 'INACTIVO', 'start_date' => '2026-01-01']);

        return [$valid, $other, $inactive];
    }

    private function jefe(): User
    {
        return User::query()->where('email', 'jefatura@example.test')->firstOrFail();
    }

    private function unit(): UnidadServicio
    {
        return $this->jefe()->unidadesHabilitadas()->orderBy('unidades_servicios.id')->firstOrFail();
    }

    private function otherUnit(): UnidadServicio
    {
        return UnidadServicio::query()->whereKeyNot($this->unit()->id)->where('activo', true)->orderByDesc('id')->firstOrFail();
    }
}
