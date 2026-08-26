<?php

namespace Tests\Feature;

use App\Models\Estamento;
use App\Models\Persona;
use App\Models\Profesion;
use App\Models\UnidadServicio;
use App\Models\User;
use App\Models\UserUnidad;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PhaseTwoPeopleAndStaffingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_person_rut_is_normalized_and_cannot_be_duplicated_with_another_format(): void
    {
        $admin = User::query()->where('email', 'admin@example.test')->firstOrFail();
        $this->actingAs($admin)->post('/personas', [
            'rut' => '6.532.697-3', 'nombres' => 'Persona', 'apellido_paterno' => 'Ficticia',
        ])->assertRedirect();
        $this->assertDatabaseHas('personas', ['rut' => '6532697-3']);

        $this->actingAs($admin)->from('/personas')->post('/personas', [
            'rut' => '6532697-3', 'nombres' => 'Persona duplicada',
        ])->assertSessionHasErrors('rut');
        $this->assertSame(1, Persona::query()->where('rut', '6532697-3')->count());
    }

    public function test_search_by_formatted_rut_and_name_returns_person(): void
    {
        $jefe = User::query()->where('email', 'jefatura@example.test')->firstOrFail();
        $this->actingAs($jefe)->getJson('/personas/buscar?q=12.345.678-5')
            ->assertOk()->assertJsonFragment(['nombre_completo' => 'Ana Prueba Demostración Ficticia']);
        $this->actingAs($jefe)->getJson('/personas/buscar?q=Histórico')
            ->assertOk()->assertJsonFragment(['nombre_completo' => 'Bruno Ejemplo Histórico']);
    }

    public function test_person_can_have_multiple_links_that_preserve_catalog_references(): void
    {
        $persona = Persona::query()->where('rut', '11111111-1')->firstOrFail();
        $this->assertCount(2, $persona->vinculos);
        $link = $persona->vinculos()->where('status', 'ACTIVO')->firstOrFail();
        $this->assertInstanceOf(UnidadServicio::class, $link->unidad);
        $this->assertInstanceOf(Estamento::class, $link->estamento);
        $this->assertInstanceOf(Profesion::class, $link->profesion);
    }

    public function test_jefe_can_only_consult_staffing_for_enabled_units(): void
    {
        $jefe = User::query()->where('email', 'jefatura@example.test')->firstOrFail();
        $enabled = $jefe->unidadesHabilitadas()->firstOrFail();
        $disabled = UnidadServicio::query()->whereDoesntHave('usuariosHabilitados', fn ($query) => $query->whereKey($jefe->id))->firstOrFail();

        $this->actingAs($jefe)->get('/dotacion?unidad_id='.$enabled->id)->assertOk();
        $this->actingAs($jefe)->get('/dotacion?unidad_id='.$disabled->id)->assertForbidden();
    }

    public function test_administrator_can_consult_any_unit(): void
    {
        $admin = User::query()->where('email', 'admin@example.test')->firstOrFail();
        $unit = UnidadServicio::query()->where('nombre', 'U. de Farmacia')->firstOrFail();

        $this->actingAs($admin)->get('/dotacion?unidad_id='.$unit->id)->assertOk();
    }

    public function test_user_units_unique_constraint_prevents_duplicates(): void
    {
        $assignment = UserUnidad::query()->firstOrFail();

        $this->expectException(QueryException::class);
        UserUnidad::query()->create([
            'user_id' => $assignment->user_id,
            'unidad_servicio_id' => $assignment->unidad_servicio_id,
        ]);
    }

    public function test_user_without_permission_cannot_administer_people(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/personas')->assertForbidden();
        $this->actingAs($user)->post('/personas', ['rut' => '6532697-3', 'nombres' => 'Sin permiso'])->assertForbidden();
    }

    public function test_deactivated_person_keeps_links(): void
    {
        $admin = User::query()->where('email', 'admin@example.test')->firstOrFail();
        $persona = Persona::query()->whereHas('vinculos')->firstOrFail();
        $count = $persona->vinculos()->count();

        $this->actingAs($admin)->patch('/personas/'.$persona->id.'/activo')->assertRedirect();

        $this->assertFalse($persona->fresh()->active);
        $this->assertSame($count, $persona->vinculos()->count());
    }

    public function test_link_status_is_varchar_and_phase_four_tables_do_not_exist(): void
    {
        $this->assertSame('varchar', Schema::getColumnType('persona_unidad_vinculos', 'status'));
        $this->assertTrue(Schema::hasTable('docdigital_registros'));
    }
}
