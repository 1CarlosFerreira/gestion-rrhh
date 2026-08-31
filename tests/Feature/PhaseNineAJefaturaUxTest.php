<?php

namespace Tests\Feature;

use App\Actions\Reemplazos\CrearReemplazo;
use App\Actions\Reemplazos\GuardarBorradorReemplazo;
use App\Models\Persona;
use App\Models\TipoTramite;
use App\Models\Tramite;
use App\Models\UnidadServicio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PhaseNineAJefaturaUxTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_jefatura_navigation_is_simplified_and_keeps_account_actions(): void
    {
        $this->actingAs($this->jefe())->get('/dashboard')->assertOk()
            ->assertSee('Inicio')->assertSee('Mis trámites')->assertSee('Dotación')
            ->assertSee('Mi perfil')->assertSee('Cerrar sesión')
            ->assertDontSee('>Personas<', false)->assertDontSee('Nuevo reemplazo')->assertDontSee('Nuevas horas extra');
    }

    public function test_dashboard_has_real_actions_indicators_and_attention_section(): void
    {
        $this->actingAs($this->jefe())->get('/dashboard')->assertOk()
            ->assertSee('Bienvenido/a, '.$this->jefe()->name)
            ->assertSee('Nueva solicitud de reemplazo')->assertSee('Horas extraordinarias')
            ->assertSee('Trámites activos')->assertSee('Requieren mi atención');
    }

    public function test_new_tramite_is_a_choice_screen_and_does_not_create_orphan_root(): void
    {
        $before = Tramite::query()->count();
        $this->actingAs($this->jefe())->get(route('tramites.create'))->assertOk()
            ->assertSee('Seleccione el trámite que desea iniciar')->assertSee('Solicitud de Reemplazo')->assertSee('Horas Extraordinarias')
            ->assertSee(route('reemplazos.create'), false)->assertSee(route('horas-extra.create'), false)->assertDontSee('trámite raíz');
        $type = TipoTramite::query()->where('codigo', 'REEMPLAZO')->firstOrFail();
        $this->actingAs($this->jefe())->post(route('tramites.store'), ['tipo_tramite_id' => $type->id, 'unidad_servicio_id' => $this->unit()->id]);
        $this->assertDatabaseCount('tramites', $before);
    }

    public function test_replacement_form_filters_employee_but_keeps_global_replacement_candidates(): void
    {
        $valid = Persona::query()->where('nombres', 'Ana Prueba')->firstOrFail();
        $other = Persona::query()->where('nombres', 'Carla Muestra')->firstOrFail();
        $tramite = app(CrearReemplazo::class)->execute($this->unit(), $this->jefe());

        $this->actingAs($this->jefe())->get(route('reemplazos.edit', $tramite))->assertOk()
            ->assertSee('Funcionario a reemplazar')->assertSee($valid->nombre_completo)
            ->assertSee($other->nombre_completo)
            ->assertSee('+ Agregar reemplazante que no está en la lista')->assertSee('Fecha de inicio')->assertSee('Fecha de término');
    }

    public function test_replacement_backend_rejects_employee_from_another_unit_and_reuses_replacement(): void
    {
        $tramite = app(CrearReemplazo::class)->execute($this->unit(), $this->jefe());
        $other = Persona::query()->where('nombres', 'Carla Muestra')->firstOrFail();
        try {
            app(GuardarBorradorReemplazo::class)->execute($tramite->load(['estadoTramite', 'reemplazo']), ['funcionario_id' => $other->id], $this->jefe());
            $this->fail('Expected unit validation.');
        } catch (ValidationException) {
            $this->assertNull($tramite->fresh()->reemplazo->funcionario_id);
        }
        app(GuardarBorradorReemplazo::class)->execute($tramite->load(['estadoTramite', 'reemplazo']), ['reemplazante_id' => $other->id], $this->jefe());
        $this->assertSame($other->id, $tramite->fresh()->reemplazo->reemplazante_id);
        $this->assertDatabaseCount('personas', Persona::query()->count());
    }

    public function test_personnel_file_respects_units_and_profile_is_in_spanish(): void
    {
        $visible = Persona::query()->where('nombres', 'Ana Prueba')->firstOrFail();
        $hidden = Persona::query()->where('nombres', 'Carla Muestra')->firstOrFail();
        $this->actingAs($this->jefe())->get(route('dotacion.show', $visible))->assertOk()->assertSee('Ficha del funcionario')->assertSee('Historial de vínculos')->assertSee('Reemplazos')->assertSee('Horas Extraordinarias');
        $this->get(route('dotacion.show', $hidden))->assertForbidden();
        $this->get(route('profile.edit'))->assertOk()->assertSee('Mi perfil')->assertSee('Información de la cuenta')->assertSee('Rol(es)')->assertSee('Último acceso')->assertSee('Unidades/Servicios habilitados')->assertSee('Cambiar contraseña')->assertDontSee('Update Password');
    }

    private function jefe(): User
    {
        return User::query()->where('email', 'jefatura@example.test')->firstOrFail();
    }

    private function unit(): UnidadServicio
    {
        return UnidadServicio::query()->where('nombre', 'U. de Emergencia Hospitalaria')->firstOrFail();
    }
}
