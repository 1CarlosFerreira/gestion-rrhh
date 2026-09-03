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
        $this->actingAs($this->solicitante)->post(route('reemplazos.send', $tramite))->assertRedirect(route('dashboard'));
        $this->assertSame('ENVIADA_GESTION_PERSONAS', $tramite->fresh()->estadoTramite->codigo);
        $this->assertNotNull($tramite->fresh()->submitted_at);
        $this->assertDatabaseHas('tramite_historial', ['tramite_id' => $tramite->id, 'user_id' => $this->solicitante->id, 'action_code' => 'ENVIAR_A_GESTION_PERSONAS']);
        $this->assertSame(9, $tramite->reemplazo->diasSinCobertura());
    }

    public function test_incomplete_draft_and_unauthorized_or_out_of_scope_users_cannot_send(): void
    {
        $this->actingAs($this->solicitante)->post(route('reemplazos.store'), ['unidad_organizacional_id' => $this->unidad->id]);
        $tramite = Tramite::query()->latest('id')->firstOrFail();
        $this->actingAs($this->solicitante)->post(route('reemplazos.send', $tramite))->assertSessionHasErrors(['funcionario_id', 'reemplazante_id', 'justificacion']);
        $withoutPermission = User::factory()->create(['active' => true]);
        $this->actingAs($withoutPermission)->post(route('reemplazos.send', $tramite))->assertForbidden();
        $withoutAccess = User::factory()->create(['active' => true]);
        $withoutAccess->givePermissionTo('reemplazos.crear');
        $this->actingAs($withoutAccess)->post(route('reemplazos.send', $tramite))->assertForbidden();
    }

    public function test_reviewer_scope_bandeja_start_and_double_transition_are_enforced(): void
    {
        $tramite = $this->sendDraft();
        $this->actingAs($this->revisor)->get(route('gestion-personas.reemplazos.index'))->assertOk()->assertSee($tramite->codigo);
        $this->actingAs($this->revisor)->post(route('gestion-personas.reemplazos.start', $tramite))->assertRedirect();
        $this->assertSame('EN_REVISION', $tramite->fresh()->estadoTramite->codigo);
        $this->assertDatabaseHas('tramite_historial', ['tramite_id' => $tramite->id, 'user_id' => $this->revisor->id, 'action_code' => 'INICIAR_REVISION']);
        $this->actingAs($this->revisor)->post(route('gestion-personas.reemplazos.start', $tramite))->assertSessionHasErrors('action_code');
        $outsider = User::factory()->create(['active' => true]);
        $outsider->givePermissionTo('reemplazos.revisar');
        $this->actingAs($outsider)->get(route('gestion-personas.reemplazos.show', $tramite))->assertForbidden();
        $withoutPermission = User::factory()->create(['active' => true]);
        UserUnidadAcceso::query()->create(['user_id' => $withoutPermission->id, 'unidad_organizacional_id' => $this->unidad->id, 'alcance' => AlcanceAccesoOperativo::SOLO_UNIDAD, 'vigente_desde' => today(), 'created_by' => $this->revisor->id]);
        $this->actingAs($withoutPermission)->get(route('gestion-personas.reemplazos.show', $tramite))->assertForbidden();
        $this->actingAs($outsider)->get(route('gestion-personas.reemplazos.index'))->assertOk()->assertDontSee($tramite->codigo);
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
        $this->actingAs($this->solicitante)->post(route('reemplazos.send', $tramite))->assertSessionHasErrors('justificacion');
        $this->assertSame('DEVUELTA_PARA_CORRECCION', $tramite->fresh()->estadoTramite->codigo);
        $tramite->reemplazo()->update(['justificacion' => 'Antecedentes corregidos.']);
        $this->actingAs($this->solicitante)->post(route('reemplazos.send', $tramite))->assertRedirect();
        $this->assertSame('ENVIADA_GESTION_PERSONAS', $tramite->fresh()->estadoTramite->codigo);
        $this->assertDatabaseHas('tramite_historial', ['tramite_id' => $tramite->id, 'action_code' => 'REENVIAR_A_GESTION_PERSONAS']);
    }

    public function test_approval_requires_administrative_data_and_does_not_generate_pdf_or_dotacion(): void
    {
        $tramite = $this->startReview();
        $this->actingAs($this->revisor)->post(route('gestion-personas.reemplazos.approve', $tramite))->assertSessionHasErrors(['grado_eus', 'clasificacion_area_id', 'cumple_normativa']);
        $classification = ClasificacionArea::query()->create(['codigo' => 'TEST', 'nombre' => 'Prueba', 'activo' => true]);
        $this->actingAs($this->revisor)->put(route('gestion-personas.reemplazos.save', $tramite), ['grado_eus' => 12, 'clasificacion_area_id' => $classification->id, 'cumple_normativa' => 0]);
        $this->actingAs($this->revisor)->post(route('gestion-personas.reemplazos.approve', $tramite))->assertRedirect();
        $this->assertSame('LISTA_GENERAR_DOCUMENTO', $tramite->fresh()->estadoTramite->codigo);
        $this->assertDatabaseHas('reemplazo_revisiones', ['tramite_id' => $tramite->id, 'revisado_por' => $this->revisor->id]);
        $this->assertDatabaseHas('tramite_historial', ['tramite_id' => $tramite->id, 'user_id' => $this->revisor->id, 'action_code' => 'APROBAR_ANTECEDENTES']);
        $this->assertSame(0, $tramite->documentosGenerados()->count());
        $this->assertNull($tramite->vinculoDotacion);
        $this->actingAs($this->solicitante)->get(route('reemplazos.edit', $tramite))->assertForbidden();
        $this->actingAs($this->solicitante)->put(route('reemplazos.update', $tramite), ['unidad_organizacional_id' => $this->unidad->id])->assertForbidden();
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
        $this->actingAs($this->solicitante)->post(route('reemplazos.send', $tramite));

        return $tramite->fresh();
    }

    private function startReview(): Tramite
    {
        $tramite = $this->sendDraft();
        $this->actingAs($this->revisor)->post(route('gestion-personas.reemplazos.start', $tramite));

        return $tramite->fresh();
    }
}
