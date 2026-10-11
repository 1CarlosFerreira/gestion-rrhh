<?php

namespace Tests\Feature;

use App\Actions\Reemplazos\SolicitarAnulacionTransitoriaAction;
use App\Enums\AlcanceAccesoOperativo;
use App\Enums\ContextoAutoridadInstitucional;
use App\Enums\ModalidadSolicitudContrato;
use App\Models\EstadoTramite;
use App\Models\Persona;
use App\Models\RespaldoAfectacion;
use App\Models\SolicitudContrato;
use App\Models\TipoReemplazo;
use App\Models\TipoTramite;
use App\Models\Tramite;
use App\Models\TramiteHistorial;
use App\Models\TramiteReemplazo;
use App\Models\UnidadOrganizacional;
use App\Models\User;
use App\Models\UserUnidadAcceso;
use App\Services\Respaldos\EscriturasPeriodosTransitorios;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SolicitarAnulacionTransitoriaTest extends TestCase
{
    use RefreshDatabase;

    private User $actor;

    private UnidadOrganizacional $unidad;

    private Persona $funcionario;

    private Persona $candidato;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->actor = User::factory()->create(['active' => true]);
        $this->actor->givePermissionTo('reemplazos.crear');
        $this->unidad = UnidadOrganizacional::query()->where('activo', true)->firstOrFail();
        UserUnidadAcceso::query()->create(['user_id' => $this->actor->id, 'unidad_organizacional_id' => $this->unidad->id, 'alcance' => AlcanceAccesoOperativo::SOLO_UNIDAD, 'vigente_desde' => today()->subDay(), 'created_by' => $this->actor->id]);
        $this->funcionario = $this->persona('89000001-1');
        $this->candidato = $this->persona('89000002-2');
    }

    public function test_request_preserves_state_commitments_and_records_immutable_history(): void
    {
        [$tramite, $respaldo] = $this->caso();
        $afectacion = RespaldoAfectacion::query()->create(['respaldo_id' => $respaldo->id, 'respaldo_version_id' => $respaldo->versionActual->id, 'solicitud_contrato_id' => $tramite->solicitudContrato->id, 'comprometido_at' => now(), 'comprometido_por' => $this->actor->id]);
        app(EscriturasPeriodosTransitorios::class)->registrarReserva($afectacion, $this->actor, $this->candidato->id, '2026-10-05', '2026-10-15');
        $envio = $this->envio($tramite);
        $evento = app(SolicitarAnulacionTransitoriaAction::class)->execute($tramite, $this->actor, '  Motivo ficticio  ');
        $this->assertSame('Motivo ficticio', $evento->observation);
        $this->assertSame($this->actor->id, $evento->user_id);
        $this->assertSame($envio->id, $evento->metadata['primer_envio_historial_id']);
        $this->assertSame($tramite->estado_tramite_id, $tramite->fresh()->estado_tramite_id);
        $this->assertNull($afectacion->fresh()->liberado_at);
        $this->assertNull($afectacion->reservas()->sole()->liberado_at);
        $this->expectException(\LogicException::class);
        $evento->update(['observation' => 'Alteración']);
    }

    public function test_returned_draft_still_has_persistent_first_submission(): void
    {
        [$tramite] = $this->caso(false);
        $this->envio($tramite);
        $tramite->update(['estado_tramite_id' => EstadoTramite::query()->where('codigo', 'BORRADOR')->sole()->id]);
        app(SolicitarAnulacionTransitoriaAction::class)->execute($tramite, $this->actor, 'Motivo');
        $this->assertSame(1, $tramite->historial()->where('action_code', 'SOLICITAR_ANULACION')->count());
    }

    public function test_current_state_and_submitted_at_do_not_replace_submission_history(): void
    {
        [$tramite] = $this->caso(false);
        $tramite->update(['submitted_at' => now()]);
        $this->expectException(ValidationException::class);
        app(SolicitarAnulacionTransitoriaAction::class)->execute($tramite, $this->actor, 'Motivo');
    }

    public function test_missing_permission_is_rejected(): void
    {
        [$tramite] = $this->caso(false);
        $this->envio($tramite);
        $this->actor->revokePermissionTo('reemplazos.crear');
        $this->expectException(AuthorizationException::class);
        app(SolicitarAnulacionTransitoriaAction::class)->execute($tramite, $this->actor, 'Motivo');
    }

    public function test_out_of_scope_operator_is_rejected(): void
    {
        [$tramite] = $this->caso(false);
        $this->envio($tramite);
        $otro = User::factory()->create(['active' => true]);
        $otro->givePermissionTo('reemplazos.crear');
        $this->expectException(AuthorizationException::class);
        app(SolicitarAnulacionTransitoriaAction::class)->execute($tramite, $otro, 'Motivo');
    }

    public function test_empty_reason_is_rejected(): void
    {
        [$tramite] = $this->caso(false);
        $this->expectException(ValidationException::class);
        app(SolicitarAnulacionTransitoriaAction::class)->execute($tramite, $this->actor, '  ');
    }

    public function test_v2_is_rejected_without_new_history(): void
    {
        $tipo = TipoTramite::query()->where('codigo', 'REEMPLAZO')->sole();
        $tramite = Tramite::query()->create(['public_id' => (string) Str::ulid(), 'codigo' => 'V2-FICTICIO', 'tipo_tramite_id' => $tipo->id, 'estado_tramite_id' => $this->estadoRevision()->id, 'unidad_organizacional_id' => $this->unidad->id, 'created_by' => $this->actor->id]);
        try {
            app(SolicitarAnulacionTransitoriaAction::class)->execute($tramite, $this->actor, 'Motivo');
            $this->fail('V2 debe rechazarse.');
        } catch (ValidationException) {
            $this->assertSame(0, $tramite->historial()->count());
        }
    }

    private function envio(Tramite $tramite): TramiteHistorial
    {
        return $tramite->historial()->create(['user_id' => $this->actor->id, 'action_code' => 'ENVIAR_A_GESTION_PERSONAS', 'to_estado_id' => $tramite->estado_tramite_id, 'occurred_at' => now()]);
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

    private function estadoRevision(): EstadoTramite
    {
        return EstadoTramite::query()->where('codigo', 'EN_REVISION')->firstOrFail();
    }

    private function persona(string $rut): Persona
    {
        return Persona::query()->create(['rut' => $rut, 'nombres' => 'Persona ficticia', 'active' => true]);
    }
}
