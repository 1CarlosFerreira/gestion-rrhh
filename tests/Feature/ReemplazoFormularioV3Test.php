<?php

namespace Tests\Feature;

use App\Enums\AlcanceAccesoOperativo;
use App\Enums\TipoResponsabilidad;
use App\Models\CalidadContractual;
use App\Models\ClasificacionArea;
use App\Models\EstadoTramite;
use App\Models\Estamento;
use App\Models\Persona;
use App\Models\PersonaUnidadVinculo;
use App\Models\TipoReemplazo;
use App\Models\Tramite;
use App\Models\UnidadOrganizacional;
use App\Models\UnidadResponsable;
use App\Models\User;
use App\Models\UserUnidadAcceso;
use App\Services\Respaldos\EscriturasPeriodosTransitorios;
use App\Support\Rut\Rut;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReemplazoFormularioV3Test extends TestCase
{
    use RefreshDatabase;

    private User $operador;

    private User $revisor;

    private UnidadOrganizacional $solicitante;

    private UnidadOrganizacional $origen;

    private UnidadOrganizacional $destino;

    private Persona $funcionario;

    private Persona $candidato;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->solicitante = UnidadOrganizacional::query()->where('codigo', 'SDGADM')->firstOrFail();
        $this->origen = UnidadOrganizacional::query()->where('codigo', 'SDGADM-INF')->firstOrFail();
        $this->destino = UnidadOrganizacional::query()->where('codigo', 'SDGDP-DGP-GP')->firstOrFail();
        $this->operador = User::factory()->create(['active' => true]);
        $this->operador->givePermissionTo('reemplazos.crear');
        $this->revisor = User::factory()->create(['active' => true]);
        $this->revisor->givePermissionTo(['reemplazos.revisar', 'reemplazos.alcance_global']);
        $this->access($this->operador, $this->solicitante, AlcanceAccesoOperativo::UNIDAD_Y_DESCENDIENTES);
        $this->access($this->operador, $this->destino, AlcanceAccesoOperativo::SOLO_UNIDAD);

        $autoridad = $this->person(1, 'Autoridad Inventada');
        $this->link($autoridad, $this->solicitante);
        UnidadResponsable::query()->create([
            'persona_id' => $autoridad->id,
            'unidad_organizacional_id' => $this->solicitante->id,
            'tipo' => TipoResponsabilidad::TITULAR,
            'vigente_desde' => today()->subYear(),
            'puede_aprobar' => true,
            'created_by' => $this->operador->id,
        ]);
        $this->funcionario = $this->person(2, 'Funcionario Inventado');
        $this->link($this->funcionario, $this->origen);
        $this->candidato = $this->person(3, 'Candidato Inventado');
    }

    public function test_form_has_three_cards_with_explicit_independent_units_and_authorized_staff(): void
    {
        $this->actingAs($this->operador)->get(route('reemplazos.create'))
            ->assertOk()
            ->assertSeeInOrder(['Antecedentes de la solicitud', 'Unidad solicitante', 'Unidad de dotación del funcionario', 'Funcionario reemplazado', 'Tipo de reemplazo', 'Período de ausencia', 'Justificación', 'Datos del Reemplazante', 'Unidad donde desempeñará funciones', 'Documentos adjuntos'])
            ->assertSee('name="unidad_origen_id"', false)
            ->assertSee('name="unidad_destino_id"', false)
            ->assertSee('name="reemplazante_id"', false)
            ->assertSee('name="nuevo_reemplazante_rut"', false);

        $this->actingAs($this->operador)->get(route('reemplazos.funcionarios', ['unidad_origen_id' => $this->origen->id, 'fecha' => today()->toDateString()]))
            ->assertOk()->assertJsonFragment(['rut' => $this->funcionario->rut]);
        $this->actingAs($this->operador)->get(route('reemplazos.funcionarios', ['unidad_origen_id' => $this->destino->id, 'fecha' => today()->toDateString()]))
            ->assertOk()->assertDontSee($this->funcionario->rut);
    }

    public function test_v3_replacement_choice_starts_with_existing_and_keeps_common_fields_below_both_options(): void
    {
        $response = $this->actingAs($this->operador)->get(route('reemplazos.create'));

        $response->assertOk()
            ->assertSeeInOrder(['Tipo de reemplazante', 'Persona existente', 'Nuevo reemplazante', 'panel-reemplazante-existente', 'Buscar persona por RUT, nombres o apellidos', 'Cambiar persona', 'panel-reemplazante-nuevo', 'Registrar nuevo reemplazante', 'Unidad donde desempeñará funciones', 'Antecedentes para este reemplazo', 'Período efectivo del reemplazante'])
            ->assertSee('name="modo_reemplazante"', false)
            ->assertSee('name="buscar_reemplazante"', false)
            ->assertSee('name="reemplazante_id" x-bind:value="personaId"', false)
            ->assertSee('x-on:input="reiniciarBusqueda()"', false)
            ->assertSee('x-on:input.debounce.350ms="buscarPersona()"', false)
            ->assertSee('x-show="sinCoincidencias"', false)
            ->assertSee('No se encontraron personas activas para esta búsqueda.')
            ->assertSee('x-on:click="$seleccionarPersonaReemplazante($data, persona)"', false)
            ->assertSee('x-bind:disabled="persona.inactiva"', false)
            ->assertSee('x-on:click="$cambiarModoReemplazante($data, \'nuevo\')"', false)
            ->assertSee('x-bind:disabled="modo !== \'existente\'"', false)
            ->assertSee('x-bind:disabled="!legacy && modo !== \'nuevo\'"', false)
            ->assertDontSee('<x-text-input', false)
            ->assertDontSee('<select name="reemplazante_id"', false)
            ->assertDontSee($this->candidato->rut)
            ->assertDontSee('aria-hidden="true">↓', false)
            ->assertDontSee('<span aria-hidden="true">+</span> Nuevo reemplazante', false);
        $this->assertMatchesRegularExpression('/modo:\s*[^\n]*existente/', $response->getContent());
    }

    public function test_validation_return_restores_new_choice_and_its_values_even_with_invalid_rut(): void
    {
        $this->actingAs($this->operador)->from(route('reemplazos.create'))->post(route('reemplazos.store'), [
            ...$this->context(),
            'modo_reemplazante' => 'nuevo',
            'nuevo_reemplazante_rut' => 'RUT inválido',
            'nuevo_reemplazante_nombres' => 'Nombre Inventado',
        ])->assertSessionHasErrors('nuevo_reemplazante_rut');

        $response = $this->actingAs($this->operador)->get(route('reemplazos.create'));
        $response->assertOk()->assertSee('Nombre Inventado')->assertSee('RUT inválido');
        $this->assertMatchesRegularExpression('/modo:\s*[^\n]*nuevo/', $response->getContent());
    }

    public function test_validation_return_restores_existing_choice_and_saved_candidate_stays_selectable(): void
    {
        $this->candidato->update(['apellido_paterno' => 'Zeta']);
        $otrasPersonas = [];
        for ($i = 100; $i <= 300; $i++) {
            $otrasPersonas[] = ['rut' => $this->rut($i), 'nombres' => 'Persona Ficticia', 'apellido_paterno' => 'Alfa', 'active' => true, 'created_at' => now(), 'updated_at' => now()];
        }
        Persona::query()->insert($otrasPersonas);
        $this->assertFalse(Persona::query()->orderBy('apellido_paterno')->limit(200)->pluck('id')->contains($this->candidato->id));

        $this->actingAs($this->operador)->from(route('reemplazos.create'))->post(route('reemplazos.store'), [
            ...$this->context(),
            'modo_reemplazante' => 'existente',
            'reemplazante_id' => $this->candidato->id,
            'buscar_reemplazante' => $this->candidato->rut,
            'fecha_funcionario_desde' => 'fecha inválida',
        ])->assertSessionHasErrors('fecha_funcionario_desde');

        $response = $this->actingAs($this->operador)->get(route('reemplazos.create'));
        $response->assertOk();
        $this->assertMatchesRegularExpression('/modo:\s*[^\n]*existente/', $response->getContent());
        $this->assertMatchesRegularExpression('/personaId:\s*[^\n]*'.$this->candidato->id.'/', $response->getContent());
        $this->assertMatchesRegularExpression('/buscar:\s*[^\n]*'.preg_quote($this->candidato->rut, '/').'/', $response->getContent());
        $response->assertSee($this->candidato->rut);

        $this->actingAs($this->operador)->post(route('reemplazos.store'), [...$this->context(), 'reemplazante_id' => $this->candidato->id])->assertRedirect();
        $tramite = Tramite::query()->firstOrFail();
        $edit = $this->actingAs($this->operador)->get(route('reemplazos.edit', $tramite));
        $edit->assertOk();
        $this->assertMatchesRegularExpression('/modo:\s*[^\n]*existente/', $edit->getContent());
        $this->assertMatchesRegularExpression('/personaId:\s*[^\n]*'.$this->candidato->id.'/', $edit->getContent());
    }

    public function test_empty_new_choice_remains_selected_after_saving_optional_candidate_draft(): void
    {
        $this->actingAs($this->operador)->post(route('reemplazos.store'), [...$this->context(), 'modo_reemplazante' => 'nuevo'])->assertRedirect();
        $tramite = Tramite::query()->firstOrFail();
        $this->assertNull($tramite->reemplazo->reemplazante_id);

        $edit = $this->actingAs($this->operador)->get(route('reemplazos.edit', $tramite));
        $edit->assertOk();
        $this->assertMatchesRegularExpression('/modo:\s*[^\n]*nuevo/', $edit->getContent());

        $this->actingAs($this->operador)->put(route('reemplazos.update', $tramite), [...$this->context(), 'modo_reemplazante' => 'nuevo'])->assertRedirect();
        $edit = $this->actingAs($this->operador)->get(route('reemplazos.edit', $tramite));
        $this->assertMatchesRegularExpression('/modo:\s*[^\n]*nuevo/', $edit->getContent());
        $this->assertNull($tramite->fresh()->reemplazo->reemplazante_id);
    }

    public function test_new_candidate_is_created_once_without_account_or_staffing_and_duplicate_rut_is_rejected(): void
    {
        $newRut = $this->rut(4);
        $payload = [
            ...$this->context(),
            'funcionario_id' => $this->funcionario->id,
            'nuevo_reemplazante_rut' => $newRut,
            'nuevo_reemplazante_nombres' => 'Persona Nueva Inventada',
        ];
        $this->actingAs($this->operador)->post(route('reemplazos.store'), $payload)->assertRedirect();
        $person = Persona::query()->where('rut', $newRut)->firstOrFail();
        $this->assertSame($person->id, Tramite::query()->firstOrFail()->reemplazo->reemplazante_id);
        $this->assertFalse(User::query()->where('persona_id', $person->id)->exists());
        $this->assertFalse(PersonaUnidadVinculo::query()->where('persona_id', $person->id)->exists());

        $edit = $this->actingAs($this->operador)->get(route('reemplazos.edit', Tramite::query()->firstOrFail()));
        $this->assertMatchesRegularExpression('/modo:\s*[^\n]*existente/', $edit->getContent());
        $this->assertMatchesRegularExpression('/personaId:\s*[^\n]*'.$person->id.'/', $edit->getContent());

        $this->actingAs($this->operador)->post(route('reemplazos.store'), $payload)->assertSessionHasErrors('nuevo_reemplazante_rut');
        $inactiva = $this->person(5, 'Persona Inactiva Inventada');
        $inactiva->update(['active' => false]);
        $this->actingAs($this->operador)->post(route('reemplazos.store'), [
            ...$this->context(),
            'nuevo_reemplazante_rut' => $inactiva->rut,
            'nuevo_reemplazante_nombres' => 'Otra Persona Inventada',
        ])->assertSessionHasErrors('nuevo_reemplazante_rut');
        $this->assertSame(1, Persona::query()->where('rut', $newRut)->count());
        $this->assertDatabaseCount('tramites', 1);
    }

    public function test_autocomplete_finds_rut_name_and_surname_with_current_unit_outside_requester_scope(): void
    {
        $unidadExterna = UnidadOrganizacional::query()->where('codigo', 'SDGDP')->firstOrFail();
        $this->candidato->update(['apellido_paterno' => 'Apellido Inventado']);
        $this->link($this->candidato, $unidadExterna);

        $this->actingAs($this->operador)->get(route('reemplazos.personas', ['buscar' => 'Candidato']))
            ->assertOk()
            ->assertJsonPath('0.id', (string) $this->candidato->id)
            ->assertJsonPath('0.rut', $this->candidato->rut)
            ->assertJsonPath('0.unidad_vigente', $unidadExterna->nombre)
            ->assertJsonMissingPath('0.antecedentes');
        $this->actingAs($this->operador)->get(route('reemplazos.personas', ['buscar' => 'Apellido']))
            ->assertOk()->assertJsonPath('0.id', (string) $this->candidato->id);
        $this->actingAs($this->operador)->get(route('reemplazos.personas', ['buscar' => substr($this->candidato->rut, 0, 6)]))
            ->assertOk()->assertJsonFragment(['rut' => $this->candidato->rut]);
        $this->actingAs($this->operador)->get(route('reemplazos.personas', ['rut' => $this->candidato->rut]))
            ->assertOk()->assertJsonFragment(['rut' => $this->candidato->rut]);
    }

    public function test_autocomplete_limits_queries_results_and_requires_authorization(): void
    {
        for ($i = 20; $i <= 34; $i++) {
            $this->person($i, 'Prueba Ficticia');
        }
        $inactiva = $this->person(35, 'Prueba Inactiva');
        $inactiva->update(['active' => false]);

        $this->getJson(route('reemplazos.personas', ['buscar' => 'Prueba']))->assertUnauthorized();
        $response = $this->actingAs($this->operador)->get(route('reemplazos.personas', ['buscar' => 'Prueba']));
        $response->assertOk();
        $this->assertCount(10, $response->json());
        $response->assertDontSee($inactiva->rut);
        $this->actingAs($this->operador)->get(route('reemplazos.personas', ['buscar' => 'Pr']))
            ->assertOk()->assertExactJson([]);
        $this->actingAs($this->operador)->get(route('reemplazos.personas', ['buscar' => 'Prueba%']))
            ->assertOk()->assertExactJson([]);
        $this->actingAs($this->operador)->get(route('reemplazos.personas', ['buscar' => str_repeat('a', 81)]))
            ->assertOk()->assertExactJson([]);
        $this->actingAs($this->operador)->get(route('reemplazos.personas', ['rut' => $inactiva->rut]))
            ->assertOk()->assertJsonPath('0.inactiva', true)->assertJsonPath('0.id', null)->assertDontSee($inactiva->nombres);
        $this->actingAs($this->operador)->get(route('reemplazos.personas', ['buscar' => $inactiva->rut]))
            ->assertOk()->assertJsonPath('0.inactiva', true);
        $outsider = User::factory()->create(['active' => true]);
        $outsider->givePermissionTo('reemplazos.crear');
        $this->actingAs($outsider)->get(route('reemplazos.personas', ['buscar' => 'Prueba']))->assertForbidden();
    }

    public function test_autocomplete_rate_limit_rejects_excessive_requests(): void
    {
        $this->actingAs($this->operador);
        for ($i = 0; $i < 60; $i++) {
            $this->get(route('reemplazos.personas', ['buscar' => 'Inventada']))->assertOk();
        }
        $this->get(route('reemplazos.personas', ['buscar' => 'Inventada']))->assertTooManyRequests();
    }

    public function test_selected_person_is_revalidated_as_active_and_can_belong_to_another_unit(): void
    {
        $unidadExterna = UnidadOrganizacional::query()->where('codigo', 'SDGDP')->firstOrFail();
        $this->link($this->candidato, $unidadExterna);

        $this->actingAs($this->operador)->post(route('reemplazos.store'), [
            ...$this->context(),
            'modo_reemplazante' => 'existente',
            'reemplazante_id' => $this->candidato->id,
            'buscar_reemplazante' => $this->candidato->rut,
        ])->assertRedirect();
        $tramite = Tramite::query()->firstOrFail();
        $this->assertSame($this->candidato->id, $tramite->reemplazo->reemplazante_id);

        $this->candidato->update(['active' => false]);
        $this->actingAs($this->operador)->put(route('reemplazos.update', $tramite), [
            ...$this->context(), 'reemplazante_id' => $this->candidato->id,
        ])->assertSessionHasErrors('reemplazante_id');
        $this->actingAs($this->operador)->put(route('reemplazos.update', $tramite), [
            ...$this->context(), 'reemplazante_id' => 999999,
        ])->assertSessionHasErrors('reemplazante_id');
        $this->assertSame($this->candidato->id, $tramite->fresh()->reemplazo->reemplazante_id);

        $outsider = User::factory()->create(['active' => true]);
        $outsider->givePermissionTo('reemplazos.crear');
        $this->actingAs($outsider)->post(route('reemplazos.store'), [
            ...$this->context(), 'reemplazante_id' => $this->funcionario->id,
        ])->assertForbidden();
    }

    public function test_draft_edit_keeps_distinct_units_and_rechecks_staffing_and_periods(): void
    {
        $this->actingAs($this->operador)->post(route('reemplazos.store'), [...$this->context(), 'funcionario_id' => $this->funcionario->id, 'fecha_funcionario_desde' => today()->toDateString(), 'fecha_funcionario_hasta' => today()->addDays(5)->toDateString()])->assertRedirect();
        $tramite = Tramite::query()->firstOrFail();
        $this->actingAs($this->operador)->put(route('reemplazos.update', $tramite), [...$this->context(), 'funcionario_id' => $this->funcionario->id, 'reemplazante_id' => $this->candidato->id, 'fecha_funcionario_desde' => today()->toDateString(), 'fecha_funcionario_hasta' => today()->addDays(5)->toDateString(), 'fecha_reemplazante_desde' => today()->addDay()->toDateString(), 'fecha_reemplazante_hasta' => today()->addDays(4)->toDateString()])->assertRedirect();
        $this->assertSame($this->solicitante->id, $tramite->fresh()->solicitudContrato->unidad_solicitante_id);
        $this->assertSame($this->origen->id, $tramite->fresh()->solicitudContrato->unidad_origen_id);
        $this->assertSame($this->destino->id, $tramite->fresh()->solicitudContrato->unidad_destino_id);

        $this->actingAs($this->operador)->put(route('reemplazos.update', $tramite), [...$this->context(), 'unidad_origen_id' => $this->destino->id, 'funcionario_id' => $this->funcionario->id])->assertSessionHasErrors('funcionario_id');
        $this->actingAs($this->operador)->put(route('reemplazos.update', $tramite), [...$this->context(), 'funcionario_id' => $this->funcionario->id, 'reemplazante_id' => $this->candidato->id, 'fecha_funcionario_desde' => today()->toDateString(), 'fecha_funcionario_hasta' => today()->addDays(5)->toDateString(), 'fecha_reemplazante_desde' => today()->toDateString(), 'fecha_reemplazante_hasta' => today()->addDays(6)->toDateString()])->assertSessionHasErrors('fecha_reemplazante_hasta');
        $this->assertSame($this->origen->id, $tramite->fresh()->solicitudContrato->unidad_origen_id);
    }

    public function test_gp_correction_requires_reason_and_records_old_and_new_values_without_touching_person_or_staffing(): void
    {
        $tramite = $this->reviewable();
        $estamento = Estamento::query()->whereKeyNot($tramite->reemplazo->reemplazante_estamento_id)->firstOrFail();
        $calidad = CalidadContractual::query()->whereKeyNot($tramite->reemplazo->reemplazante_calidad_contractual_id)->firstOrFail();
        $original = $tramite->reemplazo->reemplazante_cargo_funcion;
        $payload = ['reemplazante_estamento_id' => $estamento->id, 'reemplazante_profesion_id' => null, 'reemplazante_calidad_contractual_id' => $calidad->id, 'reemplazante_cargo_funcion' => 'Función corregida'];

        $this->actingAs($this->revisor)->put(route('gestion-personas.reemplazos.save', $tramite), $payload)->assertSessionHasErrors('motivo_correccion');
        $this->assertSame($original, $tramite->fresh()->reemplazo->reemplazante_cargo_funcion);
        $this->assertSame(0, $tramite->historial()->where('action_code', 'ANTECEDENTES_REEMPLAZANTE_CORREGIDOS')->count());

        $this->actingAs($this->revisor)->put(route('gestion-personas.reemplazos.save', $tramite), [...$payload, 'motivo_correccion' => 'Corrección ficticia de antecedentes'])->assertRedirect();
        $event = $tramite->historial()->where('action_code', 'ANTECEDENTES_REEMPLAZANTE_CORREGIDOS')->sole();
        $this->assertSame($this->revisor->id, $event->user_id);
        $this->assertSame($original, $event->metadata['correcciones']['reemplazante_cargo_funcion']['anterior']['valor']);
        $this->assertSame('Función corregida', $event->metadata['correcciones']['reemplazante_cargo_funcion']['nuevo']['valor']);
        $this->assertSame('Corrección ficticia de antecedentes', $event->observation);
        $this->assertSame('Función corregida', $tramite->fresh()->reemplazo->reemplazante_cargo_funcion);
        $this->assertFalse(User::query()->where('persona_id', $this->candidato->id)->exists());
        $this->assertFalse(PersonaUnidadVinculo::query()->where('persona_id', $this->candidato->id)->exists());
        $this->actingAs($this->revisor)->get(route('gestion-personas.reemplazos.show', $tramite))->assertOk()->assertSee('Historial de correcciones')->assertSee('Corrección ficticia de antecedentes');
    }

    public function test_gp_correction_rejects_unauthorized_user_and_state_after_review(): void
    {
        $tramite = $this->reviewable();
        $payload = ['reemplazante_cargo_funcion' => 'Cambio no autorizado', 'motivo_correccion' => 'Prueba ficticia'];
        $this->actingAs($this->operador)->put(route('gestion-personas.reemplazos.save', $tramite), $payload)->assertForbidden();
        $this->assertSame('Cargo original', $tramite->fresh()->reemplazo->reemplazante_cargo_funcion);

        $tramite->update(['estado_tramite_id' => EstadoTramite::query()->where('codigo', 'LISTA_GENERAR_DOCUMENTO')->firstOrFail()->id]);
        $this->actingAs($this->revisor)->put(route('gestion-personas.reemplazos.save', $tramite), $payload)->assertSessionHasErrors('revision');
        $this->assertSame('Cargo original', $tramite->fresh()->reemplazo->reemplazante_cargo_funcion);
        $this->assertSame(0, $tramite->historial()->where('action_code', 'ANTECEDENTES_REEMPLAZANTE_CORREGIDOS')->count());
    }

    public function test_document_card_uses_private_attachment_versioning_and_logical_annulment(): void
    {
        Storage::fake('private');
        $this->operador->givePermissionTo(['tramites.adjuntos.cargar', 'tramites.adjuntos.descargar', 'tramites.adjuntos.anular']);
        $this->actingAs($this->operador)->post(route('reemplazos.store'), $this->context())->assertRedirect();
        $tramite = Tramite::query()->firstOrFail();

        $this->actingAs($this->operador)->post(route('reemplazos.adjuntos.store', $tramite), [
            'archivo' => UploadedFile::fake()->create('respaldo-v3.pdf', 20, 'application/pdf'),
        ])->assertRedirect();
        $first = $tramite->adjuntos()->firstOrFail();
        $this->actingAs($this->operador)->get(route('reemplazos.edit', $tramite))
            ->assertOk()->assertSee('respaldo-v3.pdf')->assertSee('Versionar')->assertSee('Anular adjunto')->assertSee('Descargar');

        $this->actingAs($this->operador)->post(route('reemplazos.adjuntos.version', [$tramite, $first]), [
            'archivo' => UploadedFile::fake()->create('respaldo-v3-corregido.pdf', 20, 'application/pdf'),
        ])->assertRedirect();
        $this->assertSame('REEMPLAZADO', $first->fresh()->status);
        $second = $tramite->adjuntos()->where('version', 2)->firstOrFail();
        $this->assertSame('ACTIVO', $second->status);
        $this->actingAs($this->operador)->patch(route('reemplazos.adjuntos.annul', [$tramite, $second]))->assertRedirect();
        $this->assertSame('ANULADO', $second->fresh()->status);
        $this->assertSame(1, $tramite->historial()->where('action_code', 'ADJUNTO_VERSIONADO')->count());
        $this->assertSame(1, $tramite->historial()->where('action_code', 'ADJUNTO_ANULADO')->count());
    }

    public function test_approval_applies_and_audits_correction_in_same_transaction(): void
    {
        $tramite = $this->reviewable();
        $respaldo = app(EscriturasPeriodosTransitorios::class)->registrarRespaldo($tramite->solicitudContrato, $this->operador, [
            'funcionario_origen_id' => $this->funcionario->id,
            'unidad_origen_id' => $this->origen->id,
            'motivo' => 'Respaldo ficticio de prueba',
            'fecha_desde' => today()->toDateString(),
            'fecha_hasta' => today()->addDays(5)->toDateString(),
        ]);

        $this->actingAs($this->revisor)->put(route('gestion-personas.reemplazos.approve', $tramite), [
            'respaldo_version_id' => $respaldo->versionActual->id,
            'grado_eus' => 12,
            'clasificacion_area_id' => ClasificacionArea::query()->firstOrFail()->id,
            'cumple_normativa' => true,
            'reemplazante_estamento_id' => $tramite->reemplazo->reemplazante_estamento_id,
            'reemplazante_calidad_contractual_id' => $tramite->reemplazo->reemplazante_calidad_contractual_id,
            'reemplazante_cargo_funcion' => 'Cargo validado en revisión',
            'motivo_correccion' => 'Ajuste administrativo ficticio',
        ])->assertRedirect();

        $this->assertSame('LISTA_GENERAR_DOCUMENTO', $tramite->fresh()->estadoTramite->codigo);
        $this->assertSame('Cargo validado en revisión', $tramite->fresh()->reemplazo->reemplazante_cargo_funcion);
        $this->assertSame(1, $tramite->historial()->where('action_code', 'ANTECEDENTES_REEMPLAZANTE_CORREGIDOS')->count());
        $this->actingAs($this->revisor)->get(route('gestion-personas.reemplazos.show', $tramite))
            ->assertOk()->assertSee('Historial de correcciones')->assertSee('Cargo original')->assertSee('Cargo validado en revisión');
    }

    private function reviewable(): Tramite
    {
        $this->actingAs($this->operador)->post(route('reemplazos.store'), [
            ...$this->context(),
            'funcionario_id' => $this->funcionario->id,
            'reemplazante_id' => $this->candidato->id,
            'reemplazante_estamento_id' => Estamento::query()->firstOrFail()->id,
            'reemplazante_calidad_contractual_id' => CalidadContractual::query()->firstOrFail()->id,
            'reemplazante_cargo_funcion' => 'Cargo original',
            'tipo_reemplazo_id' => TipoReemplazo::query()->firstOrFail()->id,
            'fecha_funcionario_desde' => today()->toDateString(),
            'fecha_funcionario_hasta' => today()->addDays(5)->toDateString(),
            'fecha_reemplazante_desde' => today()->toDateString(),
            'fecha_reemplazante_hasta' => today()->addDays(5)->toDateString(),
            'justificacion' => 'Justificación ficticia',
        ])->assertRedirect();
        $tramite = Tramite::query()->firstOrFail();
        $tramite->update(['estado_tramite_id' => EstadoTramite::query()->where('codigo', 'EN_REVISION')->firstOrFail()->id]);

        return $tramite->refresh();
    }

    private function context(): array
    {
        return ['unidad_solicitante_id' => $this->solicitante->id, 'unidad_origen_id' => $this->origen->id, 'unidad_destino_id' => $this->destino->id];
    }

    private function access(User $user, UnidadOrganizacional $unit, AlcanceAccesoOperativo $scope): void
    {
        UserUnidadAcceso::query()->create(['user_id' => $user->id, 'unidad_organizacional_id' => $unit->id, 'alcance' => $scope, 'vigente_desde' => today()->subDay(), 'created_by' => $user->id]);
    }

    private function link(Persona $person, UnidadOrganizacional $unit): void
    {
        PersonaUnidadVinculo::query()->create(['persona_id' => $person->id, 'unidad_organizacional_id' => $unit->id, 'estamento_id' => Estamento::query()->firstOrFail()->id, 'calidad_contractual_id' => CalidadContractual::query()->firstOrFail()->id, 'cargo_funcion' => 'Cargo ficticio', 'cargo_funcion_normalizado' => 'cargo ficticio', 'vigente_desde' => today()->subYear(), 'origen' => 'MANUAL', 'created_by' => $this->operador->id]);
    }

    private function person(int $serial, string $name): Persona
    {
        $rut = $this->rut($serial);
        $this->assertTrue(Rut::validate($rut));

        return Persona::query()->create(['rut' => $rut, 'nombres' => $name, 'active' => true]);
    }

    private function rut(int $serial): string
    {
        $body = (string) (98000000 + $serial);
        $sum = 0;
        $factor = 2;
        for ($i = strlen($body) - 1; $i >= 0; $i--) {
            $sum += ((int) $body[$i]) * $factor;
            $factor = $factor === 7 ? 2 : $factor + 1;
        }
        $check = 11 - ($sum % 11);

        return $body.'-'.match ($check) {
            11 => '0', 10 => 'K', default => (string) $check
        };
    }
}
