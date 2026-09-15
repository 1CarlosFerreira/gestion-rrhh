<?php

namespace Tests\Feature;

use App\Enums\AlcanceAccesoOperativo;
use App\Models\CalidadContractual;
use App\Models\ClasificacionArea;
use App\Models\EstadoTramite;
use App\Models\Estamento;
use App\Models\Persona;
use App\Models\PersonaUnidadVinculo;
use App\Models\TipoReemplazo;
use App\Models\Tramite;
use App\Models\UnidadOrganizacional;
use App\Models\User;
use App\Models\UserUnidadAcceso;
use App\Services\Reemplazos\ReemplazoService;
use App\Services\Reemplazos\ReemplazoWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ReemplazosV2CWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $solicitante;

    private User $revisor;

    private UnidadOrganizacional $unidad;

    private Persona $funcionario;

    private Persona $reemplazante;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Permission::findOrCreate('reemplazos.crear');
        Permission::findOrCreate('reemplazos.revisar');
        Permission::findOrCreate('tramites.ver_todos');
        $this->solicitante = User::factory()->create(['active' => true]);
        $this->solicitante->givePermissionTo('reemplazos.crear');
        $this->revisor = User::factory()->create(['active' => true]);
        $this->revisor->givePermissionTo('reemplazos.revisar');
        $this->unidad = UnidadOrganizacional::query()->where('codigo', 'SDGADM-INF')->firstOrFail();
        foreach ([$this->solicitante, $this->revisor] as $user) {
            UserUnidadAcceso::query()->create(['user_id' => $user->id, 'unidad_organizacional_id' => $this->unidad->id, 'alcance' => AlcanceAccesoOperativo::SOLO_UNIDAD, 'vigente_desde' => today(), 'created_by' => $user->id]);
        }
        $this->funcionario = Persona::query()->create(['rut' => '70000201-8', 'nombres' => 'Funcionario', 'active' => true]);
        $this->reemplazante = Persona::query()->create(['rut' => '70000202-6', 'nombres' => 'Reemplazante', 'active' => true]);
        $calidad = CalidadContractual::query()->create(['codigo' => 'V2C_TEST', 'nombre' => 'Prueba', 'activo' => true]);
        PersonaUnidadVinculo::query()->create(['persona_id' => $this->funcionario->id, 'unidad_organizacional_id' => $this->unidad->id, 'estamento_id' => Estamento::query()->firstOrFail()->id, 'calidad_contractual_id' => $calidad->id, 'cargo_funcion' => 'Cargo', 'cargo_funcion_normalizado' => 'cargo', 'vigente_desde' => today()->subYear(), 'origen' => 'MANUAL', 'created_by' => $this->solicitante->id]);
    }

    public function test_complete_partial_draft_is_sent_and_history_is_recorded(): void
    {
        $tramite = $this->draft();
        $this->actingAs($this->solicitante)->put(route('reemplazos.send', $tramite), $this->draftData($tramite))->assertRedirect(route('dashboard'));
        $this->assertSame('ENVIADA_GESTION_PERSONAS', $tramite->fresh()->estadoTramite->codigo);
        $this->assertNotNull($tramite->fresh()->submitted_at);
        $this->assertDatabaseHas('tramite_historial', ['tramite_id' => $tramite->id, 'user_id' => $this->solicitante->id, 'action_code' => 'ENVIAR_A_GESTION_PERSONAS']);
        $this->assertSame(9, $tramite->reemplazo->diasSinCobertura());
    }

    public function test_sending_persists_current_justification_and_other_form_changes(): void
    {
        $tramite = $this->draft();
        $otroTipo = TipoReemplazo::query()->whereKeyNot($tramite->reemplazo->tipo_reemplazo_id)->firstOrFail();
        $datos = $this->draftData($tramite, [
            'tipo_reemplazo_id' => $otroTipo->id,
            'fecha_reemplazante_hasta' => '2026-09-24',
            'justificacion' => 'Justificación escrita al enviar.',
        ]);

        $this->actingAs($this->solicitante)->put(route('reemplazos.send', $tramite), $datos)->assertRedirect(route('dashboard'));

        $tramite->refresh();
        $this->assertSame('ENVIADA_GESTION_PERSONAS', $tramite->estadoTramite->codigo);
        $this->assertDatabaseHas('tramite_reemplazos', [
            'tramite_id' => $tramite->id,
            'tipo_reemplazo_id' => $otroTipo->id,
            'fecha_reemplazante_hasta' => '2026-09-24 00:00:00',
            'justificacion' => 'Justificación escrita al enviar.',
        ]);
    }

    public function test_incomplete_send_stays_draft_rolls_back_and_preserves_old_input(): void
    {
        $this->actingAs($this->solicitante)->post(route('reemplazos.store'), ['unidad_organizacional_id' => $this->unidad->id]);
        $tramite = Tramite::query()->latest('id')->firstOrFail();
        $datos = [
            'unidad_organizacional_id' => $this->unidad->id,
            'funcionario_id' => $this->funcionario->id,
            'justificacion' => 'Texto todavía incompleto.',
        ];

        $response = $this->actingAs($this->solicitante)
            ->from(route('reemplazos.edit', $tramite))
            ->put(route('reemplazos.send', $tramite), $datos);
        $response->assertRedirect(route('reemplazos.edit', $tramite))
            ->assertSessionHasErrors(['tipo_reemplazo_id', 'reemplazante_id'])
            ->assertSessionHasInput('justificacion', 'Texto todavía incompleto.');

        $tramite->refresh();
        $this->assertSame('BORRADOR', $tramite->estadoTramite->codigo);
        $this->assertNull($tramite->reemplazo->funcionario_id);
        $this->assertNull($tramite->reemplazo->justificacion);
        $this->actingAs($this->solicitante)->get(route('reemplazos.edit', $tramite))
            ->assertOk()
            ->assertSee('Texto todavía incompleto.');
    }

    public function test_save_draft_still_accepts_partial_data_without_sending(): void
    {
        $this->actingAs($this->solicitante)->post(route('reemplazos.store'), ['unidad_organizacional_id' => $this->unidad->id]);
        $tramite = Tramite::query()->latest('id')->firstOrFail();

        $this->actingAs($this->solicitante)->put(route('reemplazos.update', $tramite), [
            'unidad_organizacional_id' => $this->unidad->id,
            'justificacion' => 'Borrador parcial.',
        ])->assertRedirect();

        $this->assertSame('BORRADOR', $tramite->fresh()->estadoTramite->codigo);
        $this->assertDatabaseHas('tramite_reemplazos', ['tramite_id' => $tramite->id, 'justificacion' => 'Borrador parcial.', 'funcionario_id' => null]);
    }

    public function test_incomplete_draft_and_unauthorized_or_out_of_scope_users_cannot_send(): void
    {
        $this->actingAs($this->solicitante)->post(route('reemplazos.store'), ['unidad_organizacional_id' => $this->unidad->id]);
        $tramite = Tramite::query()->latest('id')->firstOrFail();
        $this->actingAs($this->solicitante)->put(route('reemplazos.send', $tramite), ['unidad_organizacional_id' => $this->unidad->id])->assertSessionHasErrors(['funcionario_id', 'reemplazante_id', 'justificacion']);
        $withoutPermission = User::factory()->create(['active' => true]);
        $this->actingAs($withoutPermission)->put(route('reemplazos.send', $tramite), ['unidad_organizacional_id' => $this->unidad->id])->assertForbidden();
        $withoutAccess = User::factory()->create(['active' => true]);
        $withoutAccess->givePermissionTo('reemplazos.crear');
        $this->actingAs($withoutAccess)->put(route('reemplazos.send', $tramite), ['unidad_organizacional_id' => $this->unidad->id])->assertForbidden();
    }

    public function test_reviewer_scope_bandeja_start_and_double_transition_are_enforced(): void
    {
        $tramite = $this->sendDraft();
        $this->actingAs($this->revisor)->get(route('gestion-personas.reemplazos.index'))
            ->assertOk()
            ->assertSee($tramite->codigo)
            ->assertSee('Revisa y gestiona las solicitudes de reemplazo recibidas.')
            ->assertSee('Pendientes de revisión')
            ->assertSee('En revisión')
            ->assertSee('Para generar documento')
            ->assertSee('Período solicitado')
            ->assertSee('Cobertura del reemplazante')
            ->assertSee('Revisar')
            ->assertDontSee('Ver/Revisar');
        $this->actingAs($this->revisor)->get(route('gestion-personas.reemplazos.show', $tramite))
            ->assertOk()
            ->assertSee('Volver a Reemplazos')
            ->assertSee('Fecha de envío')
            ->assertSee('Antecedentes de la solicitud')
            ->assertSee('Períodos y cobertura')
            ->assertSee('Días sin cobertura')
            ->assertSee('Documentos')
            ->assertSee('Iniciar revisión');
        $this->actingAs($this->revisor)->post(route('gestion-personas.reemplazos.start', $tramite))->assertRedirect();
        $this->assertSame('EN_REVISION', $tramite->fresh()->estadoTramite->codigo);
        $this->actingAs($this->revisor)->get(route('gestion-personas.reemplazos.show', $tramite))
            ->assertOk()
            ->assertSeeInOrder([
                $tramite->codigo,
                'Revisión Gestión de Personas',
                'Devolver para corrección',
                'Ver antecedentes de la solicitud',
                'Antecedentes de la solicitud',
            ])
            ->assertSee('<details class="group', false)
            ->assertDontSee('<details open', false)
            ->assertSee('Áreas críticas')
            ->assertSee('Áreas Semi-Críticas')
            ->assertSee('Áreas de apoyo Asistencial')
            ->assertSee('Área de Apoyo Administrativo y no crítico');
        $this->assertDatabaseHas('tramite_historial', ['tramite_id' => $tramite->id, 'user_id' => $this->revisor->id, 'action_code' => 'INICIAR_REVISION']);
        $this->actingAs($this->revisor)->post(route('gestion-personas.reemplazos.start', $tramite))->assertSessionHasErrors('action_code');
        $outsider = User::factory()->create(['active' => true]);
        $outsider->givePermissionTo('reemplazos.revisar');
        $this->actingAs($outsider)->get(route('gestion-personas.reemplazos.show', $tramite))->assertForbidden();
        $withoutPermission = User::factory()->create(['active' => true]);
        UserUnidadAcceso::query()->create(['user_id' => $withoutPermission->id, 'unidad_organizacional_id' => $this->unidad->id, 'alcance' => AlcanceAccesoOperativo::SOLO_UNIDAD, 'vigente_desde' => today(), 'created_by' => $this->revisor->id]);
        $this->actingAs($withoutPermission)->get(route('gestion-personas.reemplazos.index'))->assertForbidden();
        $this->actingAs($withoutPermission)->get(route('gestion-personas.reemplazos.show', $tramite))->assertForbidden();
        $this->actingAs($withoutPermission)->post(route('gestion-personas.reemplazos.start', $tramite))->assertForbidden();
        $this->actingAs($outsider)->get(route('gestion-personas.reemplazos.index'))
            ->assertOk()
            ->assertDontSee($tramite->codigo)
            ->assertSee('No hay solicitudes pendientes.');
    }

    public function test_global_reviewer_lists_opens_and_starts_without_operational_access(): void
    {
        $tramite = $this->sendDraft();
        $global = User::factory()->create(['active' => true]);
        $global->givePermissionTo(['reemplazos.revisar', 'tramites.ver_todos']);

        $this->assertCount(0, $global->accesosOperativos);
        $this->actingAs($global)->get(route('gestion-personas.reemplazos.index'))
            ->assertOk()
            ->assertSee($tramite->codigo);
        $this->actingAs($global)->get(route('gestion-personas.reemplazos.show', $tramite))->assertOk();
        $this->actingAs($global)->post(route('gestion-personas.reemplazos.start', $tramite))->assertRedirect(route('gestion-personas.reemplazos.show', $tramite));
        $this->assertSame('EN_REVISION', $tramite->fresh()->estadoTramite->codigo);
    }

    public function test_reviewer_bandeja_filters_server_side_and_preserves_query_string(): void
    {
        $tramite = $this->sendDraft();

        foreach ([$tramite->codigo, 'Funcionario', $this->funcionario->rut, 'Reemplazante', $this->reemplazante->rut] as $buscar) {
            $this->actingAs($this->revisor)
                ->get(route('gestion-personas.reemplazos.index', ['buscar' => $buscar]))
                ->assertOk()
                ->assertSee($tramite->codigo);
        }

        $this->actingAs($this->revisor)
            ->get(route('gestion-personas.reemplazos.index', [
                'buscar' => 'sin coincidencias',
                'estado' => 'ENVIADA_GESTION_PERSONAS',
                'unidad_id' => $this->unidad->id,
            ]))
            ->assertOk()
            ->assertSee('No se encontraron resultados.')
            ->assertSee('Limpiar filtros')
            ->assertViewHas('tramites', function ($tramites): bool {
                parse_str((string) parse_url($tramites->url(2), PHP_URL_QUERY), $query);

                return $tramites->perPage() === 25
                    && $query['buscar'] === 'sin coincidencias'
                    && $query['estado'] === 'ENVIADA_GESTION_PERSONAS'
                    && (int) $query['unidad_id'] === $this->unidad->id;
            });

        $this->actingAs($this->revisor)
            ->get(route('gestion-personas.reemplazos.index', ['estado' => 'DOCUMENTO_GENERADO']))
            ->assertOk()
            ->assertDontSee($tramite->codigo)
            ->assertSee('No se encontraron resultados.');
    }

    public function test_scoped_reviewer_cannot_list_open_or_start_an_unassigned_unit(): void
    {
        $tramite = $this->sendDraft();
        $otraUnidad = UnidadOrganizacional::query()->whereKeyNot($this->unidad->id)->where('activo', true)->firstOrFail();
        $tramite->update(['unidad_organizacional_id' => $otraUnidad->id]);

        $this->actingAs($this->revisor)->get(route('gestion-personas.reemplazos.index'))
            ->assertOk()
            ->assertDontSee($tramite->codigo);
        $this->actingAs($this->revisor)->get(route('gestion-personas.reemplazos.show', $tramite))->assertForbidden();
        $this->actingAs($this->revisor)->post(route('gestion-personas.reemplazos.start', $tramite))->assertForbidden();
        $this->assertSame('ENVIADA_GESTION_PERSONAS', $tramite->fresh()->estadoTramite->codigo);
    }

    public function test_return_requires_observation_preserves_review_and_allows_edit_and_resend(): void
    {
        $tramite = $this->startReview();
        $classification = ClasificacionArea::query()->create(['codigo' => 'TEST', 'nombre' => 'Prueba', 'activo' => true]);
        $this->actingAs($this->revisor)->put(route('gestion-personas.reemplazos.save', $tramite), ['grado_eus' => 15, 'clasificacion_area_id' => $classification->id, 'cumple_normativa' => 1])->assertRedirect();
        $this->actingAs($this->revisor)->post(route('gestion-personas.reemplazos.return', $tramite), [])->assertSessionHasErrors('observation');
        $this->actingAs($this->revisor)->post(route('gestion-personas.reemplazos.return', $tramite), ['observation' => 'Corregir período.'])->assertRedirect();
        $this->assertSame('DEVUELTA_PARA_CORRECCION', $tramite->fresh()->estadoTramite->codigo);
        $this->assertDatabaseHas('reemplazo_revisiones', ['tramite_id' => $tramite->id, 'grado_eus' => 15]);
        $this->assertDatabaseHas('tramite_historial', ['tramite_id' => $tramite->id, 'user_id' => $this->revisor->id, 'action_code' => 'DEVOLVER_PARA_CORRECCION', 'observation' => 'Corregir período.']);
        $this->actingAs($this->solicitante)->get(route('reemplazos.edit', $tramite))->assertOk()->assertSee('Corregir período.');
        $this->actingAs($this->solicitante)->put(route('gestion-personas.reemplazos.save', $tramite), ['grado_eus' => 1])->assertForbidden();
        $this->assertDatabaseHas('reemplazo_revisiones', ['tramite_id' => $tramite->id, 'grado_eus' => 15]);
        $tramite->reemplazo()->update(['justificacion' => null]);
        $this->actingAs($this->solicitante)->put(route('reemplazos.send', $tramite), $this->draftData($tramite, ['justificacion' => null]))->assertSessionHasErrors('justificacion');
        $this->assertSame('DEVUELTA_PARA_CORRECCION', $tramite->fresh()->estadoTramite->codigo);
        $this->actingAs($this->solicitante)->put(route('reemplazos.send', $tramite), $this->draftData($tramite, ['justificacion' => 'Antecedentes corregidos.']))->assertRedirect();
        $this->assertSame('ENVIADA_GESTION_PERSONAS', $tramite->fresh()->estadoTramite->codigo);
        $this->assertDatabaseHas('tramite_historial', ['tramite_id' => $tramite->id, 'action_code' => 'REENVIAR_A_GESTION_PERSONAS']);
    }

    public function test_approval_saves_current_administrative_data_and_transitions_directly(): void
    {
        $tramite = $this->startReview();
        $classification = ClasificacionArea::query()->where('codigo', 'AREA-CRITICA')->firstOrFail();
        $datos = [
            'grado_eus' => 12,
            'clasificacion_area_id' => $classification->id,
            'cumple_normativa' => 0,
            'observacion_administrativa' => 'Antecedentes ingresados al aprobar.',
        ];

        $this->actingAs($this->revisor)
            ->put(route('gestion-personas.reemplazos.approve', $tramite), $datos)
            ->assertRedirect(route('gestion-personas.reemplazos.index'));

        $this->assertSame('LISTA_GENERAR_DOCUMENTO', $tramite->fresh()->estadoTramite->codigo);
        $this->assertDatabaseHas('reemplazo_revisiones', [
            'tramite_id' => $tramite->id,
            ...$datos,
            'revisado_por' => $this->revisor->id,
        ]);
        $this->assertDatabaseHas('tramite_historial', ['tramite_id' => $tramite->id, 'user_id' => $this->revisor->id, 'action_code' => 'APROBAR_ANTECEDENTES']);
        $this->assertSame(0, $tramite->documentosGenerados()->count());
        $this->assertNull($tramite->vinculoDotacion);
        $this->actingAs($this->solicitante)->get(route('reemplazos.edit', $tramite))->assertForbidden();
        $this->actingAs($this->solicitante)->put(route('reemplazos.update', $tramite), ['unidad_organizacional_id' => $this->unidad->id])->assertForbidden();
    }

    public function test_failed_direct_approval_rolls_back_and_preserves_old_input(): void
    {
        $tramite = $this->startReview();
        $classification = ClasificacionArea::query()->where('codigo', 'AREA-SEMI-CRITICA')->firstOrFail();
        $datos = [
            'grado_eus' => 18,
            'clasificacion_area_id' => $classification->id,
            'observacion_administrativa' => 'Texto que debe conservarse.',
        ];

        $this->actingAs($this->revisor)
            ->from(route('gestion-personas.reemplazos.show', $tramite))
            ->put(route('gestion-personas.reemplazos.approve', $tramite), $datos)
            ->assertRedirect(route('gestion-personas.reemplazos.show', $tramite))
            ->assertSessionHasErrors('cumple_normativa')
            ->assertSessionHasInput('grado_eus', 18)
            ->assertSessionHasInput('clasificacion_area_id', $classification->id)
            ->assertSessionHasInput('observacion_administrativa', 'Texto que debe conservarse.');

        $this->assertSame('EN_REVISION', $tramite->fresh()->estadoTramite->codigo);
        $this->assertDatabaseMissing('reemplazo_revisiones', ['tramite_id' => $tramite->id]);

        $this->actingAs($this->revisor)->get(route('gestion-personas.reemplazos.show', $tramite))
            ->assertOk()
            ->assertSee('value="18"', false)
            ->assertSee('value="'.$classification->id.'" selected', false)
            ->assertSee('Texto que debe conservarse.')
            ->assertSee('Debe informar el cumplimiento de normativa.');
    }

    public function test_overlap_filter_uses_every_active_workflow_state(): void
    {
        $this->assertSame(['BORRADOR', 'ENVIADA_GESTION_PERSONAS', 'EN_REVISION', 'DEVUELTA_PARA_CORRECCION', 'LISTA_GENERAR_DOCUMENTO'], ReemplazoWorkflow::ESTADOS_ACTIVOS);
        $service = app(ReemplazoService::class);
        $workflow = app(ReemplazoWorkflow::class);
        foreach (ReemplazoWorkflow::ESTADOS_ACTIVOS as $index => $codigo) {
            $funcionario = Persona::query()->create(['rut' => '7100000'.($index + 1).'-'.($index + 1), 'nombres' => $codigo, 'active' => true]);
            $tramite = Tramite::query()->create(['public_id' => (string) Str::ulid(), 'codigo' => 'V2C-'.$index, 'tipo_tramite_id' => EstadoTramite::query()->where('codigo', $codigo)->firstOrFail()->tipo_tramite_id, 'estado_tramite_id' => EstadoTramite::query()->where('codigo', $codigo)->firstOrFail()->id, 'unidad_organizacional_id' => $this->unidad->id, 'created_by' => $this->solicitante->id]);
            $tramite->reemplazo()->create(['funcionario_id' => $funcionario->id, 'reemplazante_id' => $this->reemplazante->id, 'tipo_reemplazo_id' => TipoReemplazo::query()->firstOrFail()->id, 'fecha_funcionario_desde' => '2026-10-01', 'fecha_funcionario_hasta' => '2026-10-10', 'fecha_reemplazante_desde' => '2026-10-01', 'fecha_reemplazante_hasta' => '2026-10-10', 'justificacion' => 'Prueba']);
            $this->assertTrue($service->existeSuperposicion($funcionario->id, '2026-10-10', '2026-10-12', null, fn ($query) => $workflow->filtrarActivos($query)), $codigo.' debe bloquear por superposición inclusiva.');
        }
    }

    private function draft(): Tramite
    {
        $this->actingAs($this->solicitante)->post(route('reemplazos.store'), ['unidad_organizacional_id' => $this->unidad->id, 'funcionario_id' => $this->funcionario->id, 'reemplazante_id' => $this->reemplazante->id, 'tipo_reemplazo_id' => TipoReemplazo::query()->firstOrFail()->id, 'fecha_funcionario_desde' => '2026-09-01', 'fecha_funcionario_hasta' => '2026-09-30', 'fecha_reemplazante_desde' => '2026-09-05', 'fecha_reemplazante_hasta' => '2026-09-25', 'justificacion' => 'Continuidad del servicio.']);

        return Tramite::query()->latest('id')->firstOrFail();
    }

    private function sendDraft(): Tramite
    {
        $tramite = $this->draft();
        $this->actingAs($this->solicitante)->put(route('reemplazos.send', $tramite), $this->draftData($tramite));

        return $tramite->fresh();
    }

    private function startReview(): Tramite
    {
        $tramite = $this->sendDraft();
        $this->actingAs($this->revisor)->post(route('gestion-personas.reemplazos.start', $tramite));

        return $tramite->fresh();
    }

    private function draftData(Tramite $tramite, array $overrides = []): array
    {
        $detalle = $tramite->reemplazo;

        return [...[
            'unidad_organizacional_id' => $tramite->unidad_organizacional_id,
            'funcionario_id' => $detalle->funcionario_id,
            'reemplazante_id' => $detalle->reemplazante_id,
            'tipo_reemplazo_id' => $detalle->tipo_reemplazo_id,
            'fecha_funcionario_desde' => $detalle->fecha_funcionario_desde?->toDateString(),
            'fecha_funcionario_hasta' => $detalle->fecha_funcionario_hasta?->toDateString(),
            'fecha_reemplazante_desde' => $detalle->fecha_reemplazante_desde?->toDateString(),
            'fecha_reemplazante_hasta' => $detalle->fecha_reemplazante_hasta?->toDateString(),
            'justificacion' => $detalle->justificacion,
        ], ...$overrides];
    }
}
