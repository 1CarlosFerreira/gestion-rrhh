<?php

namespace Tests\Feature;

use App\Actions\Reemplazos\CrearReemplazo;
use App\Actions\Reemplazos\EnviarReemplazo;
use App\Actions\Reemplazos\GenerarSolicitudReemplazoPdfAction;
use App\Actions\Reemplazos\GuardarBorradorReemplazo;
use App\Actions\Tramites\TransicionarTramite;
use App\Models\ClasificacionArea;
use App\Models\Estamento;
use App\Models\Persona;
use App\Models\Profesion;
use App\Models\TipoReemplazo;
use App\Models\Tramite;
use App\Models\UnidadServicio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GestionPersonasExperienceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Storage::fake('private');
    }

    public function test_gestion_personas_has_its_own_navigation_and_dashboard(): void
    {
        $sent = $this->sentReplacement();
        $draft = app(CrearReemplazo::class)->execute($this->unit(), $this->jefe());

        $response = $this->actingAs($this->gp())->get(route('dashboard'));

        $response->assertOk()
            ->assertSee('Bandeja de solicitudes')
            ->assertSee('Personas y dotación')
            ->assertSee('Nuevas por revisar')
            ->assertSee('Últimas solicitudes recibidas')
            ->assertSee($sent->codigo)
            ->assertDontSee($draft->codigo)
            ->assertDontSee('Mis trámites')
            ->assertDontSee('Borradores')
            ->assertDontSee('DocDigital');
    }

    public function test_bandeja_only_shows_received_replacements_and_state_filter_is_clickable(): void
    {
        $sent = $this->sentReplacement();
        $draft = app(CrearReemplazo::class)->execute($this->unit(), $this->jefe());

        $this->actingAs($this->gp())->get(route('gestion-personas.bandeja'))
            ->assertOk()
            ->assertSee('Bandeja de solicitudes')
            ->assertSee($sent->codigo)
            ->assertDontSee($draft->codigo)
            ->assertSee(route('gestion-personas.bandeja', ['estado_grupo' => 'nuevas_por_revisar']), false);

        $this->actingAs($this->gp())->get(route('tramites.show', $draft))->assertForbidden();

        $this->actingAs($this->gp())->get(route('gestion-personas.bandeja', ['estado_grupo' => 'nuevas_por_revisar']))
            ->assertOk()
            ->assertSee($sent->codigo)
            ->assertDontSee($draft->codigo);
    }

    public function test_in_review_replacement_requires_personnel_attention_and_identifies_reviewer(): void
    {
        $tramite = $this->sentReplacement();
        app(TransicionarTramite::class)->execute($tramite, 'INICIAR_REVISION', $this->gp());

        $response = $this->actingAs($this->gp())->get(route('dashboard'));

        $response->assertOk()
            ->assertSee('Requieren mi atención')
            ->assertSee($tramite->codigo)
            ->assertSee('Continuar revisión')
            ->assertSee('En revisión por '.$this->gp()->name);
    }

    public function test_starting_review_redirects_to_the_administrative_review_workspace(): void
    {
        $tramite = $this->sentReplacement();

        $this->actingAs($this->gp())->post(route('reemplazos.review.start', $tramite))
            ->assertRedirect(route('reemplazos.review.show', $tramite))
            ->assertSessionHas('status', 'Revisión iniciada correctamente.');

        $this->assertDatabaseHas('tramite_historial', ['tramite_id' => $tramite->id, 'user_id' => $this->gp()->id, 'action_code' => 'INICIAR_REVISION']);
        $this->assertSame('EN_REVISION', $tramite->fresh('estadoTramite')->estadoTramite->codigo);

        $this->actingAs($this->gp())->post(route('reemplazos.review.start', $tramite))->assertRedirect(route('gestion-personas.bandeja'));
        $this->assertSame(1, $tramite->historial()->where('action_code', 'INICIAR_REVISION')->count());

        $this->actingAs($this->gp())->get(route('reemplazos.review.show', $tramite))
            ->assertOk()
            ->assertSee('Revisión de Solicitud de Reemplazo')
            ->assertSee('Guardar avance')
            ->assertSee('Devolver para corrección')
            ->assertDontSee('DocDigital');
        $this->actingAs($this->jefe())->get(route('reemplazos.review.show', $tramite))->assertForbidden();
    }

    public function test_review_can_be_saved_partially_then_completed_once_all_fields_are_present(): void
    {
        $tramite = $this->inReview();
        $classification = ClasificacionArea::query()->firstOrFail();

        $this->actingAs($this->gp())->put(route('reemplazos.review.save', $tramite), [
            'clasificacion_area_id' => $classification->id,
            'cumple_normativa' => 1,
        ])->assertRedirect();
        $this->assertDatabaseCount('reemplazo_revisiones_personal', 1);
        $this->assertSame('EN_REVISION', $tramite->fresh('estadoTramite')->estadoTramite->codigo);
        $historyCount = $tramite->historial()->where('action_code', 'REEMPLAZO_REVISION_ACTUALIZADA')->count();
        $this->actingAs($this->gp())->put(route('reemplazos.review.save', $tramite), [
            'clasificacion_area_id' => $classification->id,
            'cumple_normativa' => 1,
        ])->assertSessionHas('status', 'No hay cambios pendientes por guardar.');
        $this->assertSame($historyCount, $tramite->historial()->where('action_code', 'REEMPLAZO_REVISION_ACTUALIZADA')->count());
        $this->actingAs($this->gp())->get(route('reemplazos.review.show', $tramite))->assertOk()->assertSee('Último guardado:');

        $this->actingAs($this->gp())->post(route('reemplazos.review.complete', $tramite))->assertSessionHasErrors('grado_eus_informado');

        $this->actingAs($this->gp())->put(route('reemplazos.review.save', $tramite), [
            'grado_eus_informado' => 13,
            'clasificacion_area_id' => $classification->id,
            'cumple_normativa' => 1,
        ])->assertRedirect();
        $this->actingAs($this->gp())->post(route('reemplazos.review.complete', $tramite))
            ->assertRedirect(route('gestion-personas.bandeja'))
            ->assertSessionHas('status', 'Revisión completada. La solicitud está lista para generar el documento.');

        $this->assertSame('LISTA_GENERAR_DOCUMENTO', $tramite->fresh('estadoTramite')->estadoTramite->codigo);
        $this->assertDatabaseCount('reemplazo_revisiones_personal', 1);
    }

    public function test_manual_grade_is_required_even_without_active_catalog_values(): void
    {
        $tramite = $this->inReview();
        $classification = ClasificacionArea::query()->firstOrFail();

        $this->actingAs($this->gp())->get(route('reemplazos.review.show', $tramite))
            ->assertOk()
            ->assertSee('Último grado E.U.S.')
            ->assertSee('Ej.: 15')
            ->assertDontSee('name="grado_eus_id"', false);

        $this->actingAs($this->gp())->put(route('reemplazos.review.save', $tramite), [
            'clasificacion_area_id' => $classification->id,
            'cumple_normativa' => 1,
            'accion' => 'aprobar',
        ])->assertSessionHasErrors('grado_eus_informado');

        $this->actingAs($this->gp())->put(route('reemplazos.review.save', $tramite), [
            'grado_eus_informado' => 15,
            'clasificacion_area_id' => $classification->id,
            'cumple_normativa' => 1,
            'accion' => 'aprobar',
        ])->assertRedirect(route('tramites.show', $tramite));

        $this->assertSame('DOCUMENTO_GENERADO', $tramite->fresh('estadoTramite')->estadoTramite->codigo);
        $this->assertSame(15, $tramite->fresh('revisionReemplazo')->revisionReemplazo->grado_eus_informado);
        $this->assertDatabaseHas('tramite_historial', ['tramite_id' => $tramite->id, 'action_code' => 'COMPLETAR_REVISION']);
        $this->actingAs($this->gp())->get(route('tramites.show', $tramite))
            ->assertOk()
            ->assertSee('Documento oficial de Solicitud de Reemplazo');
    }

    public function test_approval_ui_uses_one_controlled_request_and_separate_loading_states(): void
    {
        $tramite = $this->inReview();
        $response = $this->actingAs($this->gp())->get(route('reemplazos.review.show', $tramite));

        $response->assertOk()
            ->assertSee('@click="openApproval()"', false)
            ->assertSee('@click="approve()"', false)
            ->assertSee(':disabled="saving || approving || !approvable"', false)
            ->assertDontSee('@click="saving = true"', false)
            ->assertDontSee('|| missingApprovalFields.length"', false)
            ->assertDontSee('form="revision-form" type="submit" name="accion" value="aprobar"', false);

        $script = file_get_contents(resource_path('js/app.js'));
        $this->assertStringContainsString("payload.set('accion', 'aprobar')", $script);
        $this->assertStringContainsString('if (this.approving || this.saving) return;', $script);
        $this->assertStringContainsString('Number.isInteger(value) && value > 0', $script);
        $this->assertStringContainsString("['0', '1', 'false', 'true']", $script);
        $this->assertStringContainsString('if (this.missingApprovalFields.length)', $script);
        $this->assertStringContainsString('finally {', $script);
    }

    public function test_ajax_approval_sends_current_unsaved_values_for_both_normative_options(): void
    {
        foreach ([1, 0] as $normativa) {
            $tramite = $this->inReview();
            $classification = ClasificacionArea::query()->firstOrFail();

            $response = $this->actingAs($this->gp())->putJson(route('reemplazos.review.save', $tramite), [
                'grado_eus_informado' => 16,
                'clasificacion_area_id' => $classification->id,
                'cumple_normativa' => $normativa,
                'accion' => 'aprobar',
            ]);

            $response->assertOk()->assertJsonPath('redirect', route('tramites.show', $tramite));
            $this->assertSame('DOCUMENTO_GENERADO', $tramite->fresh('estadoTramite')->estadoTramite->codigo);
            $this->assertSame(16, $tramite->fresh()->revisionReemplazo->grado_eus_informado);
            $this->assertSame((bool) $normativa, $tramite->fresh()->revisionReemplazo->cumple_normativa);
            $this->assertSame($classification->id, $tramite->fresh()->revisionReemplazo->clasificacion_area_id);
            $this->assertSame(1, $tramite->documentosGenerados()->count());
            $this->assertSame(1, $tramite->historial()->where('action_code', 'COMPLETAR_REVISION')->count());
        }
    }

    public function test_ajax_approval_validation_returns_422_without_changing_state(): void
    {
        $tramite = $this->inReview();

        $this->actingAs($this->gp())->putJson(route('reemplazos.review.save', $tramite), [
            'accion' => 'aprobar',
        ])->assertUnprocessable()->assertJsonValidationErrors([
            'grado_eus_informado',
            'clasificacion_area_id',
            'cumple_normativa',
        ]);

        $this->assertSame('EN_REVISION', $tramite->fresh('estadoTramite')->estadoTramite->codigo);
        $this->assertDatabaseCount('documentos_generados', 0);
    }

    public function test_ajax_pdf_failure_returns_recoverable_error_without_duplicate_approval(): void
    {
        $tramite = $this->inReview();
        $classification = ClasificacionArea::query()->firstOrFail();
        $this->mock(GenerarSolicitudReemplazoPdfAction::class, function ($mock): void {
            $mock->shouldReceive('execute')->once()->andThrow(new \RuntimeException('Falla PDF ficticia'));
        });

        $this->actingAs($this->gp())->putJson(route('reemplazos.review.save', $tramite), [
            'grado_eus_informado' => 15,
            'clasificacion_area_id' => $classification->id,
            'cumple_normativa' => 1,
            'accion' => 'aprobar',
        ])->assertStatus(500)->assertJsonPath('redirect', route('tramites.show', $tramite));

        $this->assertSame('LISTA_GENERAR_DOCUMENTO', $tramite->fresh('estadoTramite')->estadoTramite->codigo);
        $this->assertSame(1, $tramite->historial()->where('action_code', 'COMPLETAR_REVISION')->count());
        $this->assertDatabaseCount('documentos_generados', 0);
    }

    public function test_returning_for_correction_requires_an_observation_and_preserves_review(): void
    {
        $tramite = $this->inReview();
        $classification = ClasificacionArea::query()->firstOrFail();
        $this->actingAs($this->gp())->put(route('reemplazos.review.save', $tramite), ['clasificacion_area_id' => $classification->id, 'cumple_normativa' => 0]);

        $this->actingAs($this->gp())->post(route('reemplazos.return', $tramite))->assertSessionHasErrors('observation');
        $this->actingAs($this->gp())->post(route('reemplazos.return', $tramite), ['observation' => 'Corrija los antecedentes ficticios antes de reenviar.'])
            ->assertRedirect(route('gestion-personas.bandeja'));

        $this->assertSame('DEVUELTA_CORRECCION', $tramite->fresh('estadoTramite')->estadoTramite->codigo);
        $this->assertDatabaseHas('tramite_historial', ['tramite_id' => $tramite->id, 'action_code' => 'DEVOLVER_CORRECCION', 'observation' => 'Corrija los antecedentes ficticios antes de reenviar.']);
        $this->assertNotNull($tramite->fresh()->revisionReemplazo);
    }

    private function sentReplacement(): Tramite
    {
        $tramite = app(CrearReemplazo::class)->execute($this->unit(), $this->jefe());
        app(GuardarBorradorReemplazo::class)->execute($tramite, [
            'tipo_reemplazo_id' => TipoReemplazo::query()->firstOrFail()->id,
            'reemplazante_id' => Persona::query()->firstOrFail()->id,
            'estamento_id' => Estamento::query()->firstOrFail()->id,
            'profesion_id' => Profesion::query()->firstOrFail()->id,
            'cargo_texto' => 'Cargo ficticio',
            'justificacion' => 'Justificación ficticia suficiente',
            'fecha_inicio' => '2026-09-01',
            'fecha_termino' => '2026-09-30',
        ], $this->jefe());

        return app(EnviarReemplazo::class)->execute($tramite->fresh(), $this->jefe());
    }

    private function inReview(): Tramite
    {
        return app(TransicionarTramite::class)->execute($this->sentReplacement(), 'INICIAR_REVISION', $this->gp());
    }

    private function jefe(): User
    {
        return User::query()->where('email', 'jefatura@example.test')->firstOrFail();
    }

    private function gp(): User
    {
        return tap(User::query()->firstOrCreate(['email' => 'gestion-experience@example.test'], ['name' => 'Gestión de Personas Ficticia', 'password' => 'password', 'active' => true]), fn (User $user) => $user->syncRoles('Gestión de Personas'));
    }

    private function unit(): UnidadServicio
    {
        return UnidadServicio::query()->where('nombre', 'U. de Emergencia Hospitalaria')->firstOrFail();
    }
}
