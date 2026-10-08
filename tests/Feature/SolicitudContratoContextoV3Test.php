<?php

namespace Tests\Feature;

use App\Actions\Reemplazos\GenerarSolicitudReemplazoPdfAction;
use App\Actions\Tramites\TransicionarTramite;
use App\Enums\AlcanceAccesoOperativo;
use App\Enums\ModalidadSolicitudContrato;
use App\Enums\TipoResponsabilidad;
use App\Models\CalidadContractual;
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
use App\Policies\TramitePolicy;
use App\Policies\TramiteReemplazoPolicy;
use App\Services\Reemplazos\BorradorReemplazoService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class SolicitudContratoContextoV3Test extends TestCase
{
    use RefreshDatabase;

    private User $operador;

    private UnidadOrganizacional $solicitante;

    private UnidadOrganizacional $origen;

    private UnidadOrganizacional $destino;

    private Persona $autoridad;

    private Persona $funcionario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Permission::findOrCreate('reemplazos.crear');
        Permission::findOrCreate('tramites.ver_todos');
        $this->operador = User::factory()->create(['active' => true]);
        $this->operador->givePermissionTo('reemplazos.crear');
        [$this->solicitante, $this->origen, $this->destino] = UnidadOrganizacional::query()->where('activo', true)->orderBy('id')->limit(3)->get()->all();
        foreach ([$this->solicitante, $this->origen, $this->destino] as $unidad) {
            $this->acceso($unidad);
        }
        $this->autoridad = $this->persona('97000001-1', 'Autoridad');
        $this->funcionario = $this->persona('97000002-2', 'Funcionario');
        $this->responsabilidad($this->autoridad, TipoResponsabilidad::SUBROGANTE);
        $this->vinculo($this->funcionario, $this->origen);
    }

    public function test_creates_explicit_independent_context_and_derives_registrador(): void
    {
        $this->actingAs($this->operador)->post(route('reemplazos.store'), [...$this->contexto(), 'funcionario_id' => $this->funcionario->id])->assertRedirect();

        $tramite = Tramite::query()->firstOrFail();
        $solicitud = $tramite->solicitudContrato;
        $this->assertSame(ModalidadSolicitudContrato::TRANSITORIA, $solicitud->modalidad);
        $this->assertSame($this->operador->id, $tramite->created_by);
        $this->assertSame($this->operador->id, $solicitud->registrador->id);
        $this->assertSame($this->solicitante->id, $tramite->unidad_organizacional_id);
        $this->assertSame($this->solicitante->id, $solicitud->unidad_solicitante_id);
        $this->assertSame($this->origen->id, $solicitud->unidad_origen_id);
        $this->assertSame($this->destino->id, $solicitud->unidad_destino_id);
        $this->assertSame($this->autoridad->id, $solicitud->autoridad_persona_id);
        $this->assertNull($this->autoridad->user);
        $this->assertDatabaseHas('tramite_historial', ['tramite_id' => $tramite->id, 'user_id' => $this->operador->id, 'action_code' => 'TRAMITE_CREADO']);
    }

    public function test_same_person_as_titular_and_subrogante_is_unambiguous_without_priority(): void
    {
        $this->responsabilidad($this->autoridad, TipoResponsabilidad::TITULAR);

        $this->actingAs($this->operador)->post(route('reemplazos.store'), $this->contexto())->assertRedirect();

        $solicitud = Tramite::query()->firstOrFail()->solicitudContrato;
        $this->assertSame($this->autoridad->id, $solicitud->autoridad_persona_id);
        $this->assertNull($solicitud->autoridad_responsabilidad_id);
        $ids = UnidadResponsable::query()->where('persona_id', $this->autoridad->id)->pluck('id')->all();
        $this->assertEqualsCanonicalizing($ids, $solicitud->tramite->historial()->where('action_code', 'TRAMITE_CREADO')->firstOrFail()->metadata['autoridad_responsabilidad_ids']);
    }

    public function test_distinct_titular_and_subrogante_require_institutional_rule(): void
    {
        $otraPersona = $this->persona('97000004-4', 'Otra autoridad');
        $this->responsabilidad($otraPersona, TipoResponsabilidad::TITULAR);

        $this->actingAs($this->operador)->post(route('reemplazos.store'), $this->contexto())
            ->assertSessionHasErrors('autoridad_institucional');
        $this->assertDatabaseCount('tramites', 0);
    }

    public function test_no_current_authority_fails_without_creating_tramite(): void
    {
        UnidadResponsable::query()->update(['vigente_hasta' => today()->subDay()]);

        $this->actingAs($this->operador)->post(route('reemplazos.store'), $this->contexto())
            ->assertSessionHasErrors('autoridad_institucional');
        $this->assertDatabaseCount('tramites', 0);
    }

    public function test_each_unit_requires_operational_scope_even_with_global_visibility(): void
    {
        $this->operador->givePermissionTo('tramites.ver_todos');
        foreach (['unidad_solicitante_id', 'unidad_origen_id', 'unidad_destino_id'] as $campo) {
            $fuera = UnidadOrganizacional::query()->whereNotIn('id', [$this->solicitante->id, $this->origen->id, $this->destino->id])->where('activo', true)->firstOrFail();
            $this->actingAs($this->operador)->post(route('reemplazos.store'), [...$this->contexto(), $campo => $fuera->id])->assertForbidden();
        }
        $this->assertDatabaseCount('tramites', 0);
    }

    public function test_v3_update_revalidates_changed_destination_without_partial_write(): void
    {
        $this->actingAs($this->operador)->post(route('reemplazos.store'), $this->contexto())->assertRedirect();
        $tramite = Tramite::query()->firstOrFail();
        $fuera = UnidadOrganizacional::query()->whereNotIn('id', [$this->solicitante->id, $this->origen->id, $this->destino->id])->where('activo', true)->firstOrFail();

        $this->actingAs($this->operador)->put(route('reemplazos.update', $tramite), [...$this->contexto(), 'unidad_destino_id' => $fuera->id])->assertForbidden();
        $this->assertSame($this->destino->id, $tramite->fresh()->solicitudContrato->unidad_destino_id);
        $this->assertDatabaseMissing('tramite_historial', ['tramite_id' => $tramite->id, 'action_code' => 'BORRADOR_ACTUALIZADO']);
    }

    public function test_partial_v3_context_never_infers_origin_or_destination_from_other_fields(): void
    {
        $this->actingAs($this->operador)->post(route('reemplazos.store'), ['unidad_solicitante_id' => $this->solicitante->id])
            ->assertSessionHasErrors(['unidad_origen_id', 'unidad_destino_id']);
        $this->assertDatabaseCount('tramites', 0);
    }

    public function test_origin_employee_is_checked_against_origin_not_requester_or_destination(): void
    {
        $this->actingAs($this->operador)->post(route('reemplazos.store'), [...$this->contexto(), 'funcionario_id' => $this->funcionario->id])->assertRedirect();

        $otro = $this->persona('97000005-5', 'Otro funcionario');
        $this->vinculo($otro, $this->solicitante);
        $this->actingAs($this->operador)->post(route('reemplazos.store'), [...$this->contexto(), 'funcionario_id' => $otro->id])->assertSessionHasErrors('funcionario_id');
    }

    public function test_direct_service_call_revalidates_scope_and_permission(): void
    {
        $fuera = UnidadOrganizacional::query()->whereNotIn('id', [$this->solicitante->id, $this->origen->id, $this->destino->id])->where('activo', true)->firstOrFail();
        $this->expectException(AuthorizationException::class);
        app(BorradorReemplazoService::class)->crear([...$this->contexto(), 'unidad_destino_id' => $fuera->id], [], $this->operador);
    }

    public function test_direct_service_filters_detail_fields_before_mass_assignment(): void
    {
        $tramite = app(BorradorReemplazoService::class)->crear($this->contexto(), ['created_at' => '2000-01-01 00:00:00', 'justificacion' => 'Dato permitido'], $this->operador);

        $this->assertSame('Dato permitido', $tramite->reemplazo->justificacion);
        $this->assertNotSame(2000, $tramite->reemplazo->created_at->year);
    }

    public function test_v3_cannot_be_downgraded_by_legacy_payload(): void
    {
        $this->actingAs($this->operador)->post(route('reemplazos.store'), $this->contexto())->assertRedirect();
        $tramite = Tramite::query()->firstOrFail();

        $this->actingAs($this->operador)->put(route('reemplazos.update', $tramite), ['unidad_organizacional_id' => $this->solicitante->id])
            ->assertSessionHasErrors('unidad_organizacional_id');
        $this->assertDatabaseCount('solicitudes_contrato', 1);
    }

    public function test_new_request_cannot_use_legacy_payload(): void
    {
        $this->actingAs($this->operador)->post(route('reemplazos.store'), ['unidad_organizacional_id' => $this->solicitante->id])
            ->assertSessionHasErrors(['unidad_organizacional_id', 'unidad_solicitante_id', 'unidad_origen_id', 'unidad_destino_id']);
        $this->assertDatabaseCount('tramites', 0);
    }

    public function test_direct_service_cannot_downgrade_v3_request(): void
    {
        $this->actingAs($this->operador)->post(route('reemplazos.store'), $this->contexto())->assertRedirect();
        $tramite = Tramite::query()->firstOrFail();

        try {
            app(BorradorReemplazoService::class)->actualizar($tramite, $this->solicitante, ['justificacion' => 'Intento de cambio'], $this->operador);
            $this->fail('La edición V3 no debe aceptar un contexto V2.');
        } catch (ValidationException) {
            $this->assertNull($tramite->fresh()->reemplazo->justificacion);
            $this->assertDatabaseMissing('tramite_historial', ['tramite_id' => $tramite->id, 'action_code' => 'BORRADOR_ACTUALIZADO']);
        }
    }

    public function test_global_visibility_does_not_grant_v3_review_scope(): void
    {
        $this->actingAs($this->operador)->post(route('reemplazos.store'), $this->contexto())->assertRedirect();
        $tramite = Tramite::query()->firstOrFail();
        Permission::findOrCreate('reemplazos.revisar');
        Permission::findOrCreate('reemplazos.alcance_global');
        $revisor = User::factory()->create(['active' => true]);
        $revisor->givePermissionTo(['reemplazos.revisar', 'tramites.ver_todos']);

        $this->assertFalse(app(TramiteReemplazoPolicy::class)->review($revisor, $tramite));
        $revisor->givePermissionTo('reemplazos.alcance_global');
        $this->assertTrue(app(TramiteReemplazoPolicy::class)->review($revisor, $tramite));

        $globalSinVisibilidad = User::factory()->create(['active' => true]);
        $globalSinVisibilidad->givePermissionTo(['reemplazos.revisar', 'reemplazos.alcance_global']);
        $this->assertTrue(app(TramitePolicy::class)->view($globalSinVisibilidad, $tramite));
        $this->actingAs($globalSinVisibilidad)->get(route('reemplazos.show', $tramite))->assertOk();
    }

    public function test_local_read_and_dashboard_require_scope_over_all_three_units(): void
    {
        $this->actingAs($this->operador)->post(route('reemplazos.store'), $this->contexto())->assertRedirect();
        $tramite = Tramite::query()->firstOrFail();
        Permission::findOrCreate('tramites.ver_unidades');
        $lector = User::factory()->create(['active' => true]);
        $lector->givePermissionTo('tramites.ver_unidades');
        $this->accesoPara($lector, $this->solicitante);

        $this->actingAs($lector)->get(route('reemplazos.show', $tramite))->assertForbidden();
        $this->actingAs($lector)->get(route('dashboard'))->assertOk()->assertDontSee($tramite->codigo);

        $this->accesoPara($lector, $this->origen);
        $this->accesoPara($lector, $this->destino);
        $this->actingAs($lector)->get(route('reemplazos.show', $tramite))->assertOk();
        $this->actingAs($lector)->get(route('dashboard'))->assertOk()->assertSee($tramite->codigo);
    }

    public function test_v3_document_is_blocked_until_its_authority_rule_is_defined(): void
    {
        $this->actingAs($this->operador)->post(route('reemplazos.store'), $this->contexto())->assertRedirect();
        Permission::findOrCreate('reemplazos.generar_documento');
        Permission::findOrCreate('reemplazos.alcance_global');
        $this->operador->givePermissionTo(['reemplazos.generar_documento', 'reemplazos.alcance_global']);

        $this->expectException(ValidationException::class);
        app(GenerarSolicitudReemplazoPdfAction::class)->execute(Tramite::query()->firstOrFail(), $this->operador);
    }

    public function test_document_attempt_cannot_transition_or_create_evidence_for_v3(): void
    {
        $this->actingAs($this->operador)->post(route('reemplazos.store'), $this->contexto())->assertRedirect();
        $tramite = Tramite::query()->firstOrFail();
        $lista = EstadoTramite::query()->where('tipo_tramite_id', $tramite->tipo_tramite_id)->where('codigo', 'LISTA_GENERAR_DOCUMENTO')->firstOrFail();
        $tramite->update(['estado_tramite_id' => $lista->id]);
        Permission::findOrCreate('reemplazos.generar_documento');
        Permission::findOrCreate('reemplazos.alcance_global');
        $this->operador->givePermissionTo(['reemplazos.generar_documento', 'reemplazos.alcance_global']);

        foreach ([fn () => app(GenerarSolicitudReemplazoPdfAction::class)->execute($tramite, $this->operador), fn () => app(TransicionarTramite::class)->execute($tramite, 'GENERAR_DOCUMENTO', $this->operador)] as $intento) {
            try {
                $intento();
                $this->fail('La generación V3 debió quedar bloqueada.');
            } catch (ValidationException) {
                $this->assertSame($lista->id, $tramite->fresh()->estado_tramite_id);
                $this->assertDatabaseCount('documentos_generados', 0);
                $this->assertDatabaseMissing('tramite_historial', ['tramite_id' => $tramite->id, 'action_code' => 'GENERAR_DOCUMENTO']);
            }
        }
    }

    public function test_document_block_does_not_prevent_v3_send_and_review(): void
    {
        $reemplazante = $this->persona('97000006-6', 'Reemplazante');
        $datos = [...$this->contexto(), 'funcionario_id' => $this->funcionario->id, 'reemplazante_id' => $reemplazante->id, 'reemplazante_estamento_id' => Estamento::query()->firstOrFail()->id, 'reemplazante_calidad_contractual_id' => CalidadContractual::query()->firstOrFail()->id, 'reemplazante_cargo_funcion' => 'Cargo de prueba', 'tipo_reemplazo_id' => TipoReemplazo::query()->firstOrFail()->id, 'fecha_funcionario_desde' => today()->toDateString(), 'fecha_funcionario_hasta' => today()->addDays(4)->toDateString(), 'fecha_reemplazante_desde' => today()->toDateString(), 'fecha_reemplazante_hasta' => today()->addDays(4)->toDateString(), 'justificacion' => 'Continuidad del servicio.'];
        $this->actingAs($this->operador)->post(route('reemplazos.store'), $datos)->assertRedirect();
        $tramite = Tramite::query()->firstOrFail();
        $this->actingAs($this->operador)->put(route('reemplazos.send', $tramite), $datos)->assertRedirect(route('dashboard'));
        $this->assertSame('ENVIADA_GESTION_PERSONAS', $tramite->fresh()->estadoTramite->codigo);

        Permission::findOrCreate('reemplazos.revisar');
        $revisor = User::factory()->create(['active' => true]);
        $revisor->givePermissionTo('reemplazos.revisar');
        foreach ([$this->solicitante, $this->origen, $this->destino] as $unidad) {
            $this->accesoPara($revisor, $unidad);
        }
        $this->actingAs($revisor)->post(route('gestion-personas.reemplazos.start', $tramite))->assertRedirect();
        $this->assertSame('EN_REVISION', $tramite->fresh()->estadoTramite->codigo);
    }

    public function test_permanente_is_representable_but_not_accepted_as_reemplazo_input(): void
    {
        $this->assertSame('PERMANENTE', ModalidadSolicitudContrato::PERMANENTE->value);
        $this->actingAs($this->operador)->post(route('reemplazos.store'), [...$this->contexto(), 'modalidad' => 'PERMANENTE'])->assertSessionHasErrors('modalidad');
        $this->assertDatabaseCount('tramites', 0);
    }

    public function test_persisted_permanente_context_cannot_use_reemplazo_operations(): void
    {
        $this->actingAs($this->operador)->post(route('reemplazos.store'), $this->contexto())->assertRedirect();
        $tramite = Tramite::query()->firstOrFail();
        $tramite->solicitudContrato->forceFill(['modalidad' => ModalidadSolicitudContrato::PERMANENTE])->save();
        $tramite->refresh();

        $this->assertFalse(app(TramiteReemplazoPolicy::class)->update($this->operador, $tramite));
        $this->actingAs($this->operador)->put(route('reemplazos.update', $tramite), $this->contexto())->assertForbidden();
        $this->actingAs($this->operador)->put(route('reemplazos.send', $tramite), $this->contexto())->assertForbidden();
        $this->assertSame('BORRADOR', $tramite->fresh()->estadoTramite->codigo);
    }

    public function test_client_cannot_attribute_authority_or_creator(): void
    {
        $falsaAutoridad = $this->persona('97000003-3', 'Falsa autoridad');
        $this->actingAs($this->operador)->post(route('reemplazos.store'), [...$this->contexto(), 'autoridad_persona_id' => $falsaAutoridad->id, 'created_by' => 99999])
            ->assertSessionHasErrors(['autoridad_persona_id', 'created_by']);
        $this->assertDatabaseCount('tramites', 0);
    }

    private function contexto(): array
    {
        return ['unidad_solicitante_id' => $this->solicitante->id, 'unidad_origen_id' => $this->origen->id, 'unidad_destino_id' => $this->destino->id];
    }

    private function persona(string $rut, string $nombre): Persona
    {
        return Persona::query()->create(['rut' => $rut, 'nombres' => $nombre, 'active' => true]);
    }

    private function acceso(UnidadOrganizacional $unidad): void
    {
        $this->accesoPara($this->operador, $unidad);
    }

    private function accesoPara(User $user, UnidadOrganizacional $unidad): void
    {
        UserUnidadAcceso::query()->create(['user_id' => $user->id, 'unidad_organizacional_id' => $unidad->id, 'alcance' => AlcanceAccesoOperativo::SOLO_UNIDAD, 'vigente_desde' => today()->subDay(), 'created_by' => $this->operador->id]);
    }

    private function responsabilidad(Persona $persona, TipoResponsabilidad $tipo): void
    {
        UnidadResponsable::query()->create(['persona_id' => $persona->id, 'unidad_organizacional_id' => $this->solicitante->id, 'tipo' => $tipo, 'vigente_desde' => today()->subDay(), 'puede_aprobar' => true, 'created_by' => $this->operador->id]);
    }

    private function vinculo(Persona $persona, UnidadOrganizacional $unidad): void
    {
        $calidad = CalidadContractual::query()->firstOrFail();
        PersonaUnidadVinculo::query()->create(['persona_id' => $persona->id, 'unidad_organizacional_id' => $unidad->id, 'estamento_id' => Estamento::query()->firstOrFail()->id, 'calidad_contractual_id' => $calidad->id, 'cargo_funcion' => 'Cargo ficticio', 'cargo_funcion_normalizado' => 'cargo ficticio', 'vigente_desde' => today()->subYear(), 'origen' => 'MANUAL', 'created_by' => $this->operador->id]);
    }
}
