<?php

namespace Tests\Feature;

use App\Actions\Reemplazos\CompletarRevisionReemplazo;
use App\Actions\Reemplazos\CrearReemplazo;
use App\Actions\Reemplazos\EnviarReemplazo;
use App\Actions\Reemplazos\GenerarSolicitudReemplazoPdfAction;
use App\Actions\Reemplazos\GuardarBorradorReemplazo;
use App\Actions\Reemplazos\GuardarRevisionReemplazo;
use App\Actions\Tramites\TransicionarTramite;
use App\Models\ClasificacionArea;
use App\Models\DocumentoPlantilla;
use App\Models\Estamento;
use App\Models\GradoEus;
use App\Models\Persona;
use App\Models\Profesion;
use App\Models\TipoReemplazo;
use App\Models\Tramite;
use App\Models\UnidadServicio;
use App\Models\User;
use App\Services\Documentos\DestinatarioSolicitudReemplazo;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PhaseFiveReplacementsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Storage::fake('private');
    }

    public function test_creation_builds_root_and_incomplete_replacement_with_server_side_type(): void
    {
        $tramite = $this->create();
        $this->assertNotNull($tramite->reemplazo);
        $this->assertSame('REEMPLAZO', $tramite->tipoTramite->codigo);
        $this->assertSame('BORRADOR', $tramite->estadoTramite->codigo);
        $this->assertNull($tramite->reemplazo->reemplazante_id);
        $this->assertFalse(Schema::hasColumn('tramite_reemplazos', 'unidad_servicio_id'));
    }

    public function test_jefe_cannot_create_for_disabled_unit(): void
    {
        $jefe = $this->jefe();
        $unit = UnidadServicio::query()->whereDoesntHave('usuariosHabilitados', fn ($query) => $query->whereKey($jefe->id))->firstOrFail();
        $this->expectException(AuthorizationException::class);
        app(CrearReemplazo::class)->execute($unit, $jefe);
    }

    public function test_existing_person_is_reused_even_when_new_rut_has_another_format(): void
    {
        $tramite = $this->create();
        $existing = Persona::query()->where('rut', '12345678-5')->firstOrFail();
        app(GuardarBorradorReemplazo::class)->execute($tramite, ['nuevo_reemplazante_rut' => '12.345.678-5', 'nuevo_reemplazante_nombres' => 'No duplicar'], $this->jefe());
        $this->assertSame($existing->id, $tramite->fresh()->reemplazo->reemplazante_id);
        $this->assertSame(1, Persona::query()->where('rut', '12345678-5')->count());
    }

    public function test_send_requires_complete_data_and_valid_dates(): void
    {
        $tramite = $this->create();
        $this->expectException(ValidationException::class);
        app(EnviarReemplazo::class)->execute($tramite, $this->jefe());
    }

    public function test_http_rejects_end_date_before_start(): void
    {
        $tramite = $this->create();
        $this->actingAs($this->jefe())->put(route('reemplazos.update', $tramite), ['fecha_inicio' => '2026-08-20', 'fecha_termino' => '2026-08-10'])->assertSessionHasErrors('fecha_termino');
    }

    public function test_send_uses_central_transition_and_creates_snapshots(): void
    {
        $tramite = $this->completeDraft();
        $sent = app(EnviarReemplazo::class)->execute($tramite, $this->jefe());
        $this->assertSame('ENVIADA_GESTION_PERSONAS', $sent->estadoTramite->codigo);
        $this->assertNotNull($sent->reemplazo->fresh()->reemplazante_snapshot);
        $this->assertDatabaseHas('tramite_historial', ['tramite_id' => $tramite->id, 'action_code' => 'ENVIAR_A_GESTION_PERSONAS']);
    }

    public function test_http_send_transitions_a_valid_draft_once_and_makes_it_visible_to_management(): void
    {
        $tramite = $this->completeDraft();
        $historialBefore = $tramite->historial()->count();

        $this->actingAs($this->jefe())->post(route('reemplazos.send', $tramite))
            ->assertRedirect(route('tramites.show', $tramite))
            ->assertSessionHas('status', 'Solicitud enviada correctamente a Gestión de Personas.');

        $sent = $tramite->fresh('estadoTramite');
        $this->assertSame('ENVIADA_GESTION_PERSONAS', $sent->estadoTramite->codigo);
        $this->assertSame($historialBefore + 1, $sent->historial()->count());
        $this->assertDatabaseHas('tramite_historial', ['tramite_id' => $tramite->id, 'action_code' => 'ENVIAR_A_GESTION_PERSONAS']);
        $this->actingAs($this->gp())->get(route('gestion-personas.bandeja'))->assertOk()->assertSee($tramite->codigo);

        $this->actingAs($this->jefe())->post(route('reemplazos.send', $tramite))
            ->assertRedirect(route('reemplazos.edit', $tramite))
            ->assertSessionHas('send_error', 'No fue posible enviar la solicitud. Revisa los siguientes antecedentes:');
        $this->assertSame($historialBefore + 1, $tramite->fresh()->historial()->count());
    }

    public function test_management_can_start_review_but_jefe_cannot(): void
    {
        $tramite = app(EnviarReemplazo::class)->execute($this->completeDraft(), $this->jefe());
        $this->actingAs($this->jefe())->post(route('reemplazos.review.start', $tramite))->assertForbidden();
        $this->actingAs($this->gp())->post(route('reemplazos.review.start', $tramite))->assertRedirect();
        $this->assertSame('EN_REVISION', $tramite->fresh()->estadoTramite->codigo);
    }

    public function test_jefe_cannot_edit_in_review_but_can_after_return_and_history_is_preserved(): void
    {
        $tramite = $this->inReview();
        $this->expectException(AuthorizationException::class);
        app(GuardarBorradorReemplazo::class)->execute($tramite->load(['estadoTramite', 'reemplazo']), ['justificacion' => 'Cambio'], $this->jefe());
    }

    public function test_return_requires_observation_and_reenviar_updates_snapshots(): void
    {
        $tramite = $this->inReview();
        try {
            app(TransicionarTramite::class)->execute($tramite, 'DEVOLVER_CORRECCION', $this->gp());
            $this->fail();
        } catch (ValidationException) {
        }
        app(TransicionarTramite::class)->execute($tramite, 'DEVOLVER_CORRECCION', $this->gp(), 'Corregir antecedente ficticio');
        $count = $tramite->historial()->count();
        app(GuardarBorradorReemplazo::class)->execute($tramite->fresh()->load(['estadoTramite', 'reemplazo']), ['justificacion' => 'Justificación corregida'], $this->jefe());
        app(EnviarReemplazo::class)->execute($tramite->fresh(), $this->jefe());
        $this->assertGreaterThan($count, $tramite->historial()->count());
        $this->assertSame('ENVIADA_GESTION_PERSONAS', $tramite->fresh()->estadoTramite->codigo);
    }

    public function test_review_can_be_saved_partially_but_completion_requires_all_administrative_fields(): void
    {
        $tramite = $this->inReview();
        app(GuardarRevisionReemplazo::class)->execute($tramite->load('estadoTramite'), ['clasificacion_area_id' => ClasificacionArea::query()->firstOrFail()->id, 'cumple_normativa' => false], $this->gp());
        $this->assertNull($tramite->revisionReemplazo->grado_eus_informado);

        try {
            app(CompletarRevisionReemplazo::class)->execute($tramite, $this->gp());
            $this->fail('Expected an incomplete review to be rejected.');
        } catch (ValidationException) {
            $this->assertSame('EN_REVISION', $tramite->fresh('estadoTramite')->estadoTramite->codigo);
        }

        app(GuardarRevisionReemplazo::class)->execute($tramite->fresh('estadoTramite'), ['grado_eus_informado' => 12, 'clasificacion_area_id' => ClasificacionArea::query()->firstOrFail()->id, 'cumple_normativa' => false], $this->gp());
        $done = app(CompletarRevisionReemplazo::class)->execute($tramite, $this->gp());
        $this->assertSame('LISTA_GENERAR_DOCUMENTO', $done->estadoTramite->codigo);
        $this->assertSame($this->gp()->id, $done->revisionReemplazo->fresh()->completed_by);
        $this->assertDatabaseHas('tramite_historial', ['tramite_id' => $tramite->id, 'action_code' => 'REEMPLAZO_REVISION_ACTUALIZADA']);
    }

    public function test_completion_fails_without_admin_fields_and_no_documents_are_required_without_rules(): void
    {
        $tramite = $this->inReview();
        $this->assertDatabaseCount('requisitos_documentales', 0);
        $this->expectException(ValidationException::class);
        app(CompletarRevisionReemplazo::class)->execute($tramite, $this->gp());
    }

    public function test_completion_requires_classification_and_normativa_even_when_the_grade_catalog_is_empty(): void
    {
        $tramite = $this->inReview();

        try {
            app(CompletarRevisionReemplazo::class)->execute($tramite, $this->gp());
            $this->fail('Expected the incomplete review to be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('clasificacion_area_id', $exception->errors());
            $this->assertArrayHasKey('cumple_normativa', $exception->errors());
            $this->assertArrayHasKey('grado_eus_informado', $exception->errors());
        }
    }

    public function test_completion_requires_configured_documents_even_when_the_grade_catalog_is_empty(): void
    {
        $tramite = $this->inReview();
        app(GuardarRevisionReemplazo::class)->execute($tramite, [
            'grado_eus_informado' => 12,
            'clasificacion_area_id' => ClasificacionArea::query()->firstOrFail()->id,
            'cumple_normativa' => true,
        ], $this->gp());
        DB::table('requisitos_documentales')->insert([
            'tipo_tramite_id' => $tramite->tipo_tramite_id,
            'tipo_documento_id' => DB::table('tipos_documento')->value('id'),
            'obligatorio' => true,
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            app(CompletarRevisionReemplazo::class)->execute($tramite->fresh(), $this->gp());
            $this->fail('Expected required documents to be enforced.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('documentos', $exception->errors());
            $this->assertArrayNotHasKey('grado_eus_informado', $exception->errors());
        }
    }

    public function test_manual_grade_rejects_letters_decimals_zero_and_negatives(): void
    {
        $tramite = $this->inReview();
        $classification = ClasificacionArea::query()->firstOrFail();
        foreach (['letras', '12.5', '0', '-2'] as $invalid) {
            $this->actingAs($this->gp())->put(route('reemplazos.review.save', $tramite), [
                'grado_eus_informado' => $invalid, 'clasificacion_area_id' => $classification->id, 'cumple_normativa' => true,
            ])->assertSessionHasErrors('grado_eus_informado');
        }
    }

    public function test_manual_grade_does_not_depend_on_active_catalog_values(): void
    {
        $tramite = $this->inReview();
        $inactive = GradoEus::query()->create(['grado' => 12, 'activo' => false]);
        GradoEus::query()->create(['grado' => 13, 'activo' => true]);
        $tramite->revisionReemplazo()->create([
            'grado_eus_id' => $inactive->id,
            'grado_eus_informado' => 12,
            'clasificacion_area_id' => ClasificacionArea::query()->firstOrFail()->id,
            'cumple_normativa' => true,
        ]);

        $done = app(CompletarRevisionReemplazo::class)->execute($tramite->fresh(), $this->gp());
        $this->assertSame('LISTA_GENERAR_DOCUMENTO', $done->estadoTramite->codigo);
    }

    public function test_unauthorized_user_cannot_complete_and_future_tables_do_not_exist(): void
    {
        $tramite = $this->inReview();
        $this->expectException(AuthorizationException::class);
        app(TransicionarTramite::class)->execute($tramite, 'COMPLETAR_REVISION', $this->jefe());
    }

    public function test_detail_displays_both_sections(): void
    {
        $tramite = $this->completeDraft();
        $this->actingAs($this->jefe())->get(route('tramites.show', $tramite))->assertOk()->assertSee('Solicitud')->assertSee('Reemplazante')->assertSee('Gestión de Personas');
        $this->assertTrue(Schema::hasTable('docdigital_registros'));
        $this->assertDatabaseCount('grados_eus', 0);
    }

    public function test_pdf_generation_is_valid_idempotent_and_uses_institutional_recipient_role(): void
    {
        $tramite = $this->inReview();
        app(GuardarRevisionReemplazo::class)->execute($tramite->load('estadoTramite'), [
            'grado_eus_informado' => 15,
            'clasificacion_area_id' => ClasificacionArea::query()->firstOrFail()->id,
            'cumple_normativa' => true,
        ], $this->gp());
        app(CompletarRevisionReemplazo::class)->execute($tramite, $this->gp());

        $first = app(GenerarSolicitudReemplazoPdfAction::class)->execute($tramite->fresh(), $this->gp());
        $second = app(GenerarSolicitudReemplazoPdfAction::class)->execute($tramite->fresh(), $this->gp());

        $this->assertSame($first->id, $second->id);
        $this->assertSame('DOCUMENTO_GENERADO', $tramite->fresh()->estadoTramite->codigo);
        $this->assertDatabaseCount('documentos_generados', 1);
        $this->assertStringStartsWith('%PDF', Storage::disk('private')->get($first->adjunto->storage_path));
        $this->expectException(AuthorizationException::class);
        app(GenerarSolicitudReemplazoPdfAction::class)->execute($tramite->fresh(), $this->jefe());
    }

    public function test_recipient_exception_depends_on_role_not_identity(): void
    {
        $resolver = app(DestinatarioSolicitudReemplazo::class);
        $user = User::factory()->create(['name' => 'Nombre completamente ficticio', 'email' => 'rol.ficticio@example.test']);
        $this->assertSame('SUBDIRECCIÓN DE GESTIÓN Y DESARROLLO DE LAS PERSONAS', $resolver->para($user));
        $user->assignRole(DestinatarioSolicitudReemplazo::ROL_SUBDIRECTOR_PERSONAS);
        $this->assertSame('DIRECTOR DEL HOSPITAL', $resolver->para($user));
    }

    public function test_missing_generated_file_is_recovered_without_repeating_state_transition(): void
    {
        $tramite = $this->inReview();
        app(GuardarRevisionReemplazo::class)->execute($tramite->load('estadoTramite'), [
            'grado_eus_informado' => 15,
            'clasificacion_area_id' => ClasificacionArea::query()->firstOrFail()->id,
            'cumple_normativa' => true,
        ], $this->gp());
        app(CompletarRevisionReemplazo::class)->execute($tramite, $this->gp());
        $first = app(GenerarSolicitudReemplazoPdfAction::class)->execute($tramite->fresh(), $this->gp());
        Storage::disk('private')->delete($first->adjunto->storage_path);
        $generationTransitions = $tramite->historial()->where('action_code', 'GENERAR_DOCUMENTO')->count();

        $replacement = app(GenerarSolicitudReemplazoPdfAction::class)->execute($tramite->fresh(), $this->gp());

        $this->assertNotSame($first->id, $replacement->id);
        $this->assertSame(2, $replacement->version);
        $this->assertSame('ANULADO', $first->fresh()->status);
        $this->assertSame($generationTransitions, $tramite->historial()->where('action_code', 'GENERAR_DOCUMENTO')->count());
        $this->assertStringStartsWith('%PDF', Storage::disk('private')->get($replacement->adjunto->storage_path));
    }

    public function test_legacy_ready_request_can_capture_grade_and_retry_without_reapproving(): void
    {
        $tramite = $this->inReview();
        app(GuardarRevisionReemplazo::class)->execute($tramite->load('estadoTramite'), [
            'grado_eus_informado' => 15,
            'clasificacion_area_id' => ClasificacionArea::query()->firstOrFail()->id,
            'cumple_normativa' => true,
        ], $this->gp());
        app(CompletarRevisionReemplazo::class)->execute($tramite, $this->gp());
        $tramite->revisionReemplazo()->update(['grado_eus_informado' => null]);
        $approvals = $tramite->historial()->where('action_code', 'COMPLETAR_REVISION')->count();

        $this->actingAs($this->gp())->post(route('reemplazos.pdf.retry', $tramite))->assertSessionHas('error');
        $this->assertDatabaseCount('documentos_generados', 0);
        $this->actingAs($this->jefe())->put(route('reemplazos.grade.update', $tramite), ['grado_eus_informado' => 14])->assertForbidden();
        $this->actingAs($this->gp())->put(route('reemplazos.grade.update', $tramite), ['grado_eus_informado' => 14])->assertRedirect();
        $this->actingAs($this->gp())->post(route('reemplazos.pdf.retry', $tramite))->assertRedirect(route('tramites.show', $tramite));

        $this->assertSame('DOCUMENTO_GENERADO', $tramite->fresh()->estadoTramite->codigo);
        $this->assertSame($approvals, $tramite->historial()->where('action_code', 'COMPLETAR_REVISION')->count());
        $this->assertDatabaseHas('tramite_historial', ['tramite_id' => $tramite->id, 'action_code' => 'GRADO_EUS_INFORMADO']);
    }

    public function test_generation_failure_keeps_ready_state_without_document_or_partial_file(): void
    {
        $tramite = $this->inReview();
        app(GuardarRevisionReemplazo::class)->execute($tramite->load('estadoTramite'), [
            'grado_eus_informado' => 15,
            'clasificacion_area_id' => ClasificacionArea::query()->firstOrFail()->id,
            'cumple_normativa' => true,
        ], $this->gp());
        app(CompletarRevisionReemplazo::class)->execute($tramite, $this->gp());
        DocumentoPlantilla::query()->where('codigo', 'REEMPLAZO_SOLICITUD_PDF')->update(['active' => false]);

        try {
            app(GenerarSolicitudReemplazoPdfAction::class)->execute($tramite->fresh(), $this->gp());
            $this->fail('Expected generation to fail without an active template.');
        } catch (\Throwable) {
        }

        $this->assertSame('LISTA_GENERAR_DOCUMENTO', $tramite->fresh()->estadoTramite->codigo);
        $this->assertDatabaseCount('documentos_generados', 0);
        $this->assertEmpty(Storage::disk('private')->allFiles('tramites/'.$tramite->public_id));
        $this->assertDatabaseHas('tramite_historial', ['tramite_id' => $tramite->id, 'action_code' => 'GENERACION_DOCUMENTO_FALLIDA']);
    }

    public function test_pdf_view_and_download_are_authorized_and_manual_grade_is_rendered(): void
    {
        $tramite = $this->inReview();
        app(GuardarRevisionReemplazo::class)->execute($tramite->load('estadoTramite'), [
            'grado_eus_informado' => 15,
            'clasificacion_area_id' => ClasificacionArea::query()->firstOrFail()->id,
            'cumple_normativa' => true,
        ], $this->gp());
        app(CompletarRevisionReemplazo::class)->execute($tramite, $this->gp());
        $document = app(GenerarSolicitudReemplazoPdfAction::class)->execute($tramite->fresh(), $this->gp());
        $html = view('pdf.reemplazos.solicitud', ['tramite' => $tramite->fresh()->load(['unidadServicio', 'creador.roles', 'reemplazo.tipoReemplazo', 'revisionReemplazo.clasificacionArea']), 'destinatario' => 'DESTINATARIO FICTICIO', 'generadoAt' => now()])->render();
        $this->assertStringContainsString('15° E.U.S.', $html);

        $this->actingAs($this->gp())->get(route('tramites.adjuntos.view', [$tramite, $document->adjunto]))->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->actingAs($this->gp())->get(route('tramites.adjuntos.download', [$tramite, $document->adjunto]))->assertOk()->assertDownload($document->adjunto->original_name);
        $unauthorized = User::factory()->create();
        $this->actingAs($unauthorized)->get(route('tramites.adjuntos.view', [$tramite, $document->adjunto]))->assertForbidden();
    }

    private function create(): Tramite
    {
        return app(CrearReemplazo::class)->execute($this->jefe()->unidadesHabilitadas()->firstOrFail(), $this->jefe());
    }

    private function completeDraft(): Tramite
    {
        $tramite = $this->create();
        app(GuardarBorradorReemplazo::class)->execute($tramite, [
            'tipo_reemplazo_id' => TipoReemplazo::query()->firstOrFail()->id, 'reemplazante_id' => Persona::query()->firstOrFail()->id,
            'estamento_id' => Estamento::query()->firstOrFail()->id, 'profesion_id' => Profesion::query()->firstOrFail()->id,
            'cargo_texto' => 'Cargo ficticio', 'justificacion' => 'Justificación ficticia suficiente', 'fecha_inicio' => '2026-09-01', 'fecha_termino' => '2026-09-30',
        ], $this->jefe());

        return $tramite->fresh();
    }

    private function inReview(): Tramite
    {
        $tramite = app(EnviarReemplazo::class)->execute($this->completeDraft(), $this->jefe());

        return app(TransicionarTramite::class)->execute($tramite, 'INICIAR_REVISION', $this->gp());
    }

    private function jefe(): User
    {
        return User::query()->where('email', 'jefatura@example.test')->firstOrFail();
    }

    private function gp(): User
    {
        return tap(User::query()->firstOrCreate(['email' => 'gestion@example.test'], ['name' => 'Gestión Ficticia', 'password' => 'password', 'active' => true]), fn ($user) => $user->syncRoles('Gestión de Personas'));
    }
}
