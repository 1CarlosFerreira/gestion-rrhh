<?php

namespace Tests\Feature;

use App\Actions\Tramites\CrearTramite;
use App\Actions\Tramites\TransicionarTramite;
use App\Models\EstadoTramite;
use App\Models\TipoTramite;
use App\Models\Tramite;
use App\Models\TransicionEstado;
use App\Models\UnidadServicio;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PhaseThreeCoreTransactionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_creation_generates_ulid_unique_code_correct_initial_state_and_history(): void
    {
        $tramite = $this->createAsAdmin('REEMPLAZO');
        $other = $this->createAsAdmin('REEMPLAZO');

        $this->assertMatchesRegularExpression('/^[0-9A-HJKMNP-TV-Z]{26}$/', $tramite->public_id);
        $this->assertMatchesRegularExpression('/^TR-\d{4}-\d{6}$/', $tramite->codigo);
        $this->assertNotSame($tramite->codigo, $other->codigo);
        $this->assertSame('BORRADOR', $tramite->estadoTramite->codigo);
        $this->assertSame('REEMPLAZO', $tramite->estadoTramite->tipoTramite->codigo);
        $this->assertDatabaseHas('tramite_historial', ['tramite_id' => $tramite->id, 'action_code' => 'TRAMITE_CREADO', 'from_estado_id' => null]);
    }

    public function test_request_cannot_impose_state(): void
    {
        $admin = $this->admin();
        $tipo = $this->type('HORAS_EXTRAORDINARIAS');
        $foreignState = EstadoTramite::query()->where('tipo_tramite_id', '!=', $tipo->id)->firstOrFail();
        $unit = UnidadServicio::query()->where('activo', true)->firstOrFail();

        $this->actingAs($admin)->post('/tramites', [
            'tipo_tramite_id' => $tipo->id,
            'unidad_servicio_id' => $unit->id,
            'estado_tramite_id' => $foreignState->id,
            'created_by' => User::query()->whereKeyNot($admin->id)->value('id'),
        ])->assertRedirect();

        $created = $admin->tramitesCreados()->latest('id')->firstOrFail();
        $this->assertSame('BORRADOR', $created->estadoTramite->codigo);
        $this->assertSame($admin->id, $created->created_by);
    }

    public function test_unit_access_is_enforced_on_creation(): void
    {
        $jefe = $this->jefe();
        $enabled = $jefe->unidadesHabilitadas()->firstOrFail();
        $disabled = UnidadServicio::query()->whereKeyNot($enabled->id)->whereDoesntHave('usuariosHabilitados', fn ($query) => $query->whereKey($jefe->id))->firstOrFail();
        $action = app(CrearTramite::class);

        $this->assertInstanceOf(Tramite::class, $action->execute($this->type('REEMPLAZO'), $enabled, $jefe));
        $this->expectException(AuthorizationException::class);
        $action->execute($this->type('REEMPLAZO'), $disabled, $jefe);
    }

    public function test_administrator_can_create_in_any_active_unit(): void
    {
        $unit = UnidadServicio::query()->where('nombre', 'U. Equipamiento Médico')->firstOrFail();
        $tramite = app(CrearTramite::class)->execute($this->type('HORAS_EXTRAORDINARIAS'), $unit, $this->admin());

        $this->assertSame($unit->id, $tramite->unidad_servicio_id);
    }

    public function test_valid_transition_changes_state_creates_history_and_sets_submitted_at_once(): void
    {
        $tramite = $this->createAsAdmin('REEMPLAZO');
        $result = app(TransicionarTramite::class)->execute($tramite, 'ENVIAR_A_GESTION_PERSONAS', $this->admin());

        $this->assertSame('ENVIADA_GESTION_PERSONAS', $result->estadoTramite->codigo);
        $this->assertNotNull($result->submitted_at);
        $this->assertDatabaseHas('tramite_historial', ['tramite_id' => $tramite->id, 'action_code' => 'ENVIAR_A_GESTION_PERSONAS']);
    }

    public function test_invalid_or_repeated_transition_changes_nothing(): void
    {
        $tramite = $this->createAsAdmin('REEMPLAZO');
        $before = $tramite->historial()->count();

        try {
            app(TransicionarTramite::class)->execute($tramite, 'ACCION_INEXISTENTE', $this->admin());
            $this->fail('Expected validation exception.');
        } catch (ValidationException) {
            $this->assertSame('BORRADOR', $tramite->fresh()->estadoTramite->codigo);
            $this->assertSame($before, $tramite->historial()->count());
        }

        app(TransicionarTramite::class)->execute($tramite, 'ENVIAR_A_GESTION_PERSONAS', $this->admin());
        $this->expectException(ValidationException::class);
        app(TransicionarTramite::class)->execute($tramite, 'ENVIAR_A_GESTION_PERSONAS', $this->admin());
    }

    public function test_required_observation_is_transactional(): void
    {
        $tramite = $this->createAsAdmin('REEMPLAZO');
        $review = EstadoTramite::query()->where(['tipo_tramite_id' => $tramite->tipo_tramite_id, 'codigo' => 'EN_REVISION'])->firstOrFail();
        $tramite->update(['estado_tramite_id' => $review->id]);
        $count = $tramite->historial()->count();

        try {
            app(TransicionarTramite::class)->execute($tramite, 'DEVOLVER_CORRECCION', $this->admin());
            $this->fail('Expected validation exception.');
        } catch (ValidationException) {
            $this->assertSame($review->id, $tramite->fresh()->estado_tramite_id);
            $this->assertSame($count, $tramite->historial()->count());
        }

        $result = app(TransicionarTramite::class)->execute($tramite, 'DEVOLVER_CORRECCION', $this->admin(), 'Observación ficticia obligatoria');
        $this->assertSame('DEVUELTA_CORRECCION', $result->estadoTramite->codigo);
        $this->assertSame('Observación ficticia obligatoria', $result->historial()->reorder()->latest('id')->value('observation'));
    }

    public function test_transition_requires_configured_permission(): void
    {
        $tramite = $this->createAsAdmin('REEMPLAZO');
        $user = User::factory()->create();
        $this->expectException(AuthorizationException::class);

        app(TransicionarTramite::class)->execute($tramite, 'ENVIAR_A_GESTION_PERSONAS', $user);
    }

    public function test_cross_type_destination_is_rejected_without_partial_history(): void
    {
        $tramite = $this->createAsAdmin('REEMPLAZO');
        $transition = TransicionEstado::query()->where(['tipo_tramite_id' => $tramite->tipo_tramite_id, 'codigo_accion' => 'ENVIAR_A_GESTION_PERSONAS'])->firstOrFail();
        $foreign = EstadoTramite::query()->where('tipo_tramite_id', '!=', $tramite->tipo_tramite_id)->firstOrFail();
        $transition->update(['estado_destino_id' => $foreign->id]);
        $count = $tramite->historial()->count();

        try {
            app(TransicionarTramite::class)->execute($tramite, 'ENVIAR_A_GESTION_PERSONAS', $this->admin());
            $this->fail('Expected validation exception.');
        } catch (ValidationException) {
            $this->assertSame($count, $tramite->historial()->count());
            $this->assertSame('BORRADOR', $tramite->fresh()->estadoTramite->codigo);
        }
    }

    public function test_formalization_requires_docdigital_evidence(): void
    {
        $tramite = $this->createAsAdmin('REEMPLAZO');
        $sent = EstadoTramite::query()->where(['tipo_tramite_id' => $tramite->tipo_tramite_id, 'codigo' => 'ENVIADA_DOCDIGITAL'])->firstOrFail();
        $tramite->update(['estado_tramite_id' => $sent->id]);

        try {
            app(TransicionarTramite::class)->execute($tramite, 'REGISTRAR_FORMALIZACION', $this->admin());
            $this->fail('Expected validation exception.');
        } catch (ValidationException) {
            $this->assertSame('ENVIADA_DOCDIGITAL', $tramite->fresh()->estadoTramite->codigo);
            $this->assertNull($tramite->fresh()->finalized_at);
        }
    }

    public function test_visibility_policy_combines_own_unit_and_global_permissions(): void
    {
        $jefe = $this->jefe();
        $own = $jefe->tramitesCreados()->firstOrFail();
        $outside = $this->createAsAdmin('REEMPLAZO', UnidadServicio::query()->where('nombre', 'U. de Farmacia')->firstOrFail());

        $this->actingAs($jefe)->get('/tramites')->assertOk()->assertSee($own->codigo)->assertDontSee($outside->codigo);
        $this->actingAs($jefe)->get(route('tramites.show', $outside))->assertForbidden();
        $this->actingAs($this->admin())->get('/tramites')->assertOk()->assertSee($own->codigo)->assertSee($outside->codigo);
    }

    public function test_list_filters_and_history_order_work(): void
    {
        $tramite = $this->createAsAdmin('REEMPLAZO');
        app(TransicionarTramite::class)->execute($tramite, 'ENVIAR_A_GESTION_PERSONAS', $this->admin());

        $this->actingAs($this->admin())->get('/tramites?codigo='.$tramite->codigo.'&tipo_tramite_id='.$tramite->tipo_tramite_id)
            ->assertOk()->assertSee($tramite->codigo);
        $response = $this->actingAs($this->admin())->get(route('tramites.show', $tramite));
        $response->assertOk()->assertSeeInOrder(['TRAMITE_CREADO', 'ENVIAR_A_GESTION_PERSONAS']);
    }

    public function test_origin_link_is_nullable_and_phase_four_tables_do_not_exist(): void
    {
        $this->assertTrue(Schema::hasColumn('persona_unidad_vinculos', 'origen_tramite_id'));
        $this->assertTrue(Schema::hasTable('docdigital_registros'));
    }

    public function test_history_events_cannot_be_modified_or_deleted_through_application_model(): void
    {
        $event = $this->createAsAdmin('REEMPLAZO')->historial()->firstOrFail();

        try {
            $event->update(['observation' => 'Cambio prohibido']);
            $this->fail('Expected immutable history exception.');
        } catch (\LogicException) {
            $this->assertNull($event->fresh()->observation);
        }

        $this->expectException(\LogicException::class);
        $event->delete();
    }

    private function createAsAdmin(string $type, ?UnidadServicio $unit = null): Tramite
    {
        return app(CrearTramite::class)->execute($this->type($type), $unit ?? UnidadServicio::query()->where('activo', true)->firstOrFail(), $this->admin());
    }

    private function admin(): User
    {
        return User::query()->where('email', 'admin@example.test')->firstOrFail();
    }

    private function jefe(): User
    {
        return User::query()->where('email', 'jefatura@example.test')->firstOrFail();
    }

    private function type(string $code): TipoTramite
    {
        return TipoTramite::query()->where('codigo', $code)->firstOrFail();
    }
}
