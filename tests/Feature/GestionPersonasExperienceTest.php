<?php

namespace Tests\Feature;

use App\Actions\Reemplazos\CrearReemplazo;
use App\Actions\Reemplazos\EnviarReemplazo;
use App\Actions\Reemplazos\GuardarBorradorReemplazo;
use App\Actions\Tramites\TransicionarTramite;
use App\Models\ClasificacionArea;
use App\Models\Estamento;
use App\Models\GradoEus;
use App\Models\Persona;
use App\Models\Profesion;
use App\Models\TipoReemplazo;
use App\Models\Tramite;
use App\Models\UnidadServicio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GestionPersonasExperienceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
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

        $grado = GradoEus::query()->create(['grado' => 13, 'activo' => true]);
        $this->actingAs($this->gp())->post(route('reemplazos.review.complete', $tramite))->assertSessionHasErrors('grado_eus_id');

        $this->actingAs($this->gp())->put(route('reemplazos.review.save', $tramite), [
            'grado_eus_id' => $grado->id,
            'clasificacion_area_id' => $classification->id,
            'cumple_normativa' => 1,
        ])->assertRedirect();
        $this->actingAs($this->gp())->post(route('reemplazos.review.complete', $tramite))
            ->assertRedirect(route('gestion-personas.bandeja'))
            ->assertSessionHas('status', 'Revisión completada. La solicitud está lista para generar el documento.');

        $this->assertSame('LISTA_GENERAR_DOCUMENTO', $tramite->fresh('estadoTramite')->estadoTramite->codigo);
        $this->assertDatabaseCount('reemplazo_revisiones_personal', 1);
    }

    public function test_review_without_active_grades_can_be_completed_and_records_the_reason(): void
    {
        $tramite = $this->inReview();
        $classification = ClasificacionArea::query()->firstOrFail();

        $this->actingAs($this->gp())->get(route('reemplazos.review.show', $tramite))
            ->assertOk()
            ->assertSee('Grado E.U.S. pendiente de configuración')
            ->assertSee('Podrás aprobar esta solicitud sin informarlo; será obligatorio cuando el catálogo esté disponible.')
            ->assertDontSee('No puedes aprobar: falta configurar el Grado E.U.S.');

        $this->actingAs($this->gp())->put(route('reemplazos.review.save', $tramite), [
            'clasificacion_area_id' => $classification->id,
            'cumple_normativa' => 1,
            'accion' => 'aprobar',
        ])->assertRedirect(route('gestion-personas.bandeja'));

        $this->assertSame('LISTA_GENERAR_DOCUMENTO', $tramite->fresh('estadoTramite')->estadoTramite->codigo);
        $this->assertNull($tramite->fresh('revisionReemplazo')->revisionReemplazo->grado_eus_id);
        $this->assertDatabaseHas('tramite_historial', [
            'tramite_id' => $tramite->id,
            'action_code' => 'COMPLETAR_REVISION',
            'observation' => 'Revisión aprobada sin Grado E.U.S. porque el catálogo no tenía valores activos.',
        ]);
        $this->actingAs($this->gp())->get(route('tramites.show', $tramite))
            ->assertOk()
            ->assertSee('Pendiente de configuración');
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
