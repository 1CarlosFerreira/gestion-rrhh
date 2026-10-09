<?php

namespace Tests\Feature;

use App\Actions\Reemplazos\AprobarAntecedentesTransitoriosAction;
use App\Actions\Tramites\TransicionarTramite;
use App\Enums\ContextoAutoridadInstitucional;
use App\Enums\ModalidadSolicitudContrato;
use App\Models\ClasificacionArea;
use App\Models\EstadoTramite;
use App\Models\Persona;
use App\Models\RespaldoAfectacion;
use App\Models\RespaldoTransitorio;
use App\Models\SolicitudContrato;
use App\Models\TipoReemplazo;
use App\Models\TipoTramite;
use App\Models\Tramite;
use App\Models\TramiteReemplazo;
use App\Models\UnidadOrganizacional;
use App\Models\User;
use App\Services\Respaldos\EscriturasPeriodosTransitorios;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AprobarAntecedentesTransitoriosTest extends TestCase
{
    use RefreshDatabase;

    private User $actor;

    private UnidadOrganizacional $unidad;

    private Persona $funcionario;

    private Persona $candidato;

    private AprobarAntecedentesTransitoriosAction $aprobar;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->actor = User::factory()->create(['active' => true]);
        $this->actor->givePermissionTo(['reemplazos.revisar', 'reemplazos.alcance_global']);
        $this->unidad = UnidadOrganizacional::query()->firstOrFail();
        $this->funcionario = $this->persona('89000001-1');
        $this->candidato = $this->persona('89000002-2');
        $this->aprobar = app(AprobarAntecedentesTransitoriosAction::class);
    }

    public function test_approval_with_candidate_commits_backing_reservation_review_state_and_history(): void
    {
        [$tramite, $respaldo] = $this->caso();
        $version = $respaldo->versionActual;

        $this->aprobar($tramite, $respaldo);

        $this->assertSame('LISTA_GENERAR_DOCUMENTO', $tramite->fresh()->estadoTramite->codigo);
        $afectacion = RespaldoAfectacion::query()->sole();
        $this->assertSame($version->id, $afectacion->respaldo_version_id);
        $this->assertSame('VIGENTE', $afectacion->estado);
        $reserva = $afectacion->reservas()->sole();
        $this->assertSame($this->candidato->id, $reserva->persona_id);
        $this->assertSame('2026-10-05', $reserva->fecha_desde->toDateString());
        $this->assertSame('2026-10-15', $reserva->fecha_hasta->toDateString());
        $this->assertSame($this->actor->id, $tramite->revisionReemplazo->revisado_por);
        $historial = $tramite->historial()->where('action_code', 'APROBAR_ANTECEDENTES')->sole();
        $this->assertSame($this->actor->id, $historial->user_id);
        $this->assertSame('EN_REVISION', $historial->estadoOrigen->codigo);
        $this->assertSame('LISTA_GENERAR_DOCUMENTO', $historial->estadoDestino->codigo);
        $this->assertSame($afectacion->id, $historial->metadata['afectacion_id']);
        $this->assertSame($version->id, $historial->metadata['respaldo_version_id']);
        $this->assertSame($reserva->id, $historial->metadata['reserva_id']);
        $this->assertSame('2026-10-05', $historial->metadata['periodo_reserva_desde']);
        $this->assertNotNull($historial->occurred_at);
    }

    public function test_approval_without_candidate_commits_only_backing_and_history(): void
    {
        [$tramite, $respaldo] = $this->caso(false);
        $this->aprobar($tramite, $respaldo);

        $this->assertDatabaseCount('respaldo_afectaciones', 1);
        $this->assertDatabaseCount('reserva_persona_periodos', 0);
        $this->assertSame('LISTA_GENERAR_DOCUMENTO', $tramite->fresh()->estadoTramite->codigo);
        $this->assertArrayNotHasKey('reserva_id', $tramite->historial()->where('action_code', 'APROBAR_ANTECEDENTES')->sole()->metadata);
    }

    public function test_committed_backing_double_approval_and_stale_version_leave_no_new_writes(): void
    {
        [$primero, $respaldo] = $this->caso();
        $this->aprobar($primero, $respaldo);
        [$segundo] = $this->caso(false, $this->persona('89000005-5'));
        $segundo->reemplazo()->update(['funcionario_id' => $this->funcionario->id]);

        $this->rechazar($segundo, $respaldo, 'respaldo');
        try {
            $this->aprobar($primero, $respaldo);
            $this->fail('La segunda aprobación debe rechazarse.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('solicitud', $exception->errors());
        }
        $this->assertDatabaseCount('respaldo_afectaciones', 1);
        $this->assertDatabaseCount('reserva_persona_periodos', 1);

        [$tercero, $otroRespaldo] = $this->caso(false, $this->persona('89000003-3'));
        $this->rechazar($tercero, $otroRespaldo, 'respaldo_version_id', 999999);
        $this->assertDatabaseCount('respaldo_afectaciones', 1);
    }

    public function test_overlapping_person_rolls_back_affectation_review_and_history(): void
    {
        [$primero, $respaldo] = $this->caso();
        $this->aprobar($primero, $respaldo);
        [$segundo, $otroRespaldo] = $this->caso(true, $this->persona('89000004-4'));

        $this->rechazar($segundo, $otroRespaldo, 'fecha_desde');
        $this->assertDatabaseCount('respaldo_afectaciones', 1);
        $this->assertDatabaseCount('reserva_persona_periodos', 1);
        $this->assertSame('EN_REVISION', $segundo->fresh()->estadoTramite->codigo);
        $this->assertSame(0, $segundo->historial()->where('action_code', 'APROBAR_ANTECEDENTES')->count());
        $this->assertNull($segundo->revisionReemplazo);
    }

    public function test_period_outside_backing_is_rejected(): void
    {
        [$tramite, $respaldo] = $this->caso();
        $tramite->reemplazo()->update(['fecha_funcionario_hasta' => '2026-10-21', 'fecha_reemplazante_hasta' => '2026-10-21']);
        $this->rechazar($tramite, $respaldo, 'fecha_funcionario_desde');
    }

    public function test_changed_backing_version_is_rejected_without_commitment(): void
    {
        [$tramite, $respaldo] = $this->caso(false);
        $anteriorId = $respaldo->versionActual->id;
        app(EscriturasPeriodosTransitorios::class)->rectificarPeriodoRespaldo($respaldo, $this->actor, '2026-10-01', '2026-10-25', 'Corrección ficticia');

        $this->rechazar($tramite, $respaldo, 'respaldo_version_id', $anteriorId);
        $this->assertDatabaseCount('respaldo_afectaciones', 0);
        $this->assertDatabaseCount('respaldo_transitorio_versiones', 2);
    }

    public function test_approval_rechecks_backing_overlap_under_person_lock(): void
    {
        [$tramite, $respaldo] = $this->caso(false);
        $otro = RespaldoTransitorio::query()->create(['solicitud_origen_id' => $tramite->solicitudContrato->id, 'created_by' => $this->actor->id]);
        $otro->versiones()->create(['version' => 1, 'funcionario_origen_id' => $this->funcionario->id, 'unidad_origen_id' => $this->unidad->id, 'motivo' => 'Duplicado ficticio externo', 'fecha_desde' => '2026-10-10', 'fecha_hasta' => '2026-10-25', 'registrado_por' => $this->actor->id]);

        $this->rechazar($tramite, $respaldo, 'respaldo');
        $this->assertDatabaseCount('respaldo_afectaciones', 0);
    }

    public function test_late_review_failure_rolls_back_backing_and_reservation(): void
    {
        [$tramite, $respaldo] = $this->caso();
        try {
            $this->aprobar->execute($tramite, $respaldo, $respaldo->versionActual->id, ['cumple_normativa' => true], $this->actor);
            $this->fail('Faltan antecedentes de la revisión.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('grado_eus', $exception->errors());
        }

        $this->assertDatabaseCount('respaldo_afectaciones', 0);
        $this->assertDatabaseCount('reserva_persona_periodos', 0);
        $this->assertNull($tramite->revisionReemplazo);
        $this->assertSame('EN_REVISION', $tramite->fresh()->estadoTramite->codigo);
    }

    public function test_inactive_and_out_of_scope_reviewers_cannot_commit(): void
    {
        [$tramite, $respaldo] = $this->caso(false);
        $this->actor->update(['active' => false]);
        try {
            $this->aprobar($tramite, $respaldo);
            $this->fail('La cuenta inactiva no debe aprobar.');
        } catch (AuthorizationException) {
            $this->assertDatabaseCount('respaldo_afectaciones', 0);
        }

        $fueraDeAlcance = User::factory()->create(['active' => true]);
        $fueraDeAlcance->givePermissionTo('reemplazos.revisar');
        $this->expectException(AuthorizationException::class);
        $this->aprobar->execute($tramite, $respaldo, $respaldo->versionActual->id, $this->revision(), $fueraDeAlcance);
    }

    public function test_wrong_state_permanent_legacy_and_unauthorized_are_rejected(): void
    {
        [$tramite, $respaldo] = $this->caso(false);
        $enviada = EstadoTramite::query()->where('tipo_tramite_id', $tramite->tipo_tramite_id)->where('codigo', 'ENVIADA_GESTION_PERSONAS')->firstOrFail();
        $tramite->update(['estado_tramite_id' => $enviada->id]);
        $this->rechazar($tramite, $respaldo, 'solicitud');
        $tramite->update(['estado_tramite_id' => $this->estadoRevision()->id]);
        $tramite->solicitudContrato()->update(['modalidad' => ModalidadSolicitudContrato::PERMANENTE]);
        $this->rechazar($tramite, $respaldo, 'solicitud');
        $tramite->solicitudContrato()->update(['modalidad' => ModalidadSolicitudContrato::TRANSITORIA]);

        $sinPermiso = User::factory()->create(['active' => true]);
        $this->expectException(AuthorizationException::class);
        $this->aprobar->execute($tramite, $respaldo, $respaldo->versionActual->id, $this->revision(), $sinPermiso);
    }

    public function test_existing_v2_route_cannot_approve_v3_without_commitment(): void
    {
        [$tramite] = $this->caso();
        $this->actingAs($this->actor)->put(route('gestion-personas.reemplazos.approve', $tramite), $this->revision())->assertSessionHasErrors('respaldo_version_id');
        $this->assertSame('EN_REVISION', $tramite->fresh()->estadoTramite->codigo);
        $this->assertDatabaseCount('respaldo_afectaciones', 0);
        $this->assertDatabaseCount('reserva_persona_periodos', 0);
        $this->assertNull($tramite->revisionReemplazo);
    }

    public function test_direct_transition_cannot_bypass_commitment(): void
    {
        [$tramite] = $this->caso();
        $this->expectException(ValidationException::class);
        app(TransicionarTramite::class)->execute($tramite, 'APROBAR_ANTECEDENTES', $this->actor);
    }

    private function aprobar(Tramite $tramite, RespaldoTransitorio $respaldo): void
    {
        $this->aprobar->execute($tramite, $respaldo, $respaldo->versionActual->id, $this->revision(), $this->actor);
    }

    private function rechazar(Tramite $tramite, RespaldoTransitorio $respaldo, string $campo, ?int $versionId = null): void
    {
        try {
            $this->aprobar->execute($tramite, $respaldo, $versionId ?? $respaldo->versionActual->id, $this->revision(), $this->actor);
            $this->fail('La aprobación debió rechazarse.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($campo, $exception->errors());
            $this->assertSame(0, $tramite->historial()->where('action_code', 'APROBAR_ANTECEDENTES')->count());
        }
    }

    private function caso(bool $conCandidato = true, ?Persona $funcionario = null): array
    {
        $funcionario ??= $this->funcionario;
        $tipo = TipoTramite::query()->where('codigo', 'REEMPLAZO')->firstOrFail();
        $tramite = Tramite::query()->create(['public_id' => (string) Str::ulid(), 'codigo' => 'C31-'.Str::upper(Str::random(12)), 'tipo_tramite_id' => $tipo->id, 'estado_tramite_id' => $this->estadoRevision()->id, 'unidad_organizacional_id' => $this->unidad->id, 'created_by' => $this->actor->id]);
        $solicitud = SolicitudContrato::query()->forceCreate(['tramite_id' => $tramite->id, 'modalidad' => ModalidadSolicitudContrato::TRANSITORIA, 'unidad_solicitante_id' => $this->unidad->id, 'autoridad_persona_id' => $funcionario->id, 'autoridad_contexto' => ContextoAutoridadInstitucional::REGISTRO_SOLICITUD_CONTRATO, 'autoridad_resuelta_at' => now(), 'unidad_origen_id' => $this->unidad->id, 'unidad_destino_id' => $this->unidad->id]);
        TramiteReemplazo::query()->create(['tramite_id' => $tramite->id, 'funcionario_id' => $funcionario->id, 'tipo_reemplazo_id' => TipoReemplazo::query()->firstOrFail()->id, 'fecha_funcionario_desde' => '2026-10-01', 'fecha_funcionario_hasta' => '2026-10-20', 'reemplazante_id' => $conCandidato ? $this->candidato->id : null, 'fecha_reemplazante_desde' => $conCandidato ? '2026-10-05' : null, 'fecha_reemplazante_hasta' => $conCandidato ? '2026-10-15' : null, 'justificacion' => 'Caso ficticio de prueba']);
        $respaldo = app(EscriturasPeriodosTransitorios::class)->registrarRespaldo($solicitud, $this->actor, ['funcionario_origen_id' => $funcionario->id, 'unidad_origen_id' => $this->unidad->id, 'motivo' => 'Antecedente ficticio', 'fecha_desde' => '2026-10-01', 'fecha_hasta' => '2026-10-20']);

        return [$tramite, $respaldo];
    }

    private function revision(): array
    {
        return ['grado_eus' => 12, 'clasificacion_area_id' => ClasificacionArea::query()->firstOrFail()->id, 'cumple_normativa' => true];
    }

    private function estadoRevision(): EstadoTramite
    {
        return EstadoTramite::query()->where('codigo', 'EN_REVISION')->firstOrFail();
    }

    private function persona(string $rut): Persona
    {
        return Persona::query()->create(['rut' => $rut, 'nombres' => 'Persona ficticia', 'active' => true]);
    }
}
