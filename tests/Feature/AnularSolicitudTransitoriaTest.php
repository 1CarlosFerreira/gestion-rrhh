<?php

namespace Tests\Feature;

use App\Actions\Reemplazos\AnularSolicitudTransitoriaAction;
use App\Actions\Reemplazos\AprobarAntecedentesTransitoriosAction;
use App\Actions\Reemplazos\SolicitarAnulacionTransitoriaAction;
use App\Actions\Tramites\TransicionarTramite;
use App\Enums\AlcanceAccesoOperativo;
use App\Enums\ContextoAutoridadInstitucional;
use App\Enums\ModalidadSolicitudContrato;
use App\Models\ClasificacionArea;
use App\Models\DocumentoGenerado;
use App\Models\DocumentoPlantilla;
use App\Models\EstadoTramite;
use App\Models\Persona;
use App\Models\ReservaPersonaPeriodo;
use App\Models\RespaldoAfectacion;
use App\Models\RespaldoTransitorio;
use App\Models\SolicitudContrato;
use App\Models\TipoReemplazo;
use App\Models\TipoTramite;
use App\Models\Tramite;
use App\Models\TramiteAdjunto;
use App\Models\TramiteHistorial;
use App\Models\TramiteReemplazo;
use App\Models\TransicionEstado;
use App\Models\UnidadOrganizacional;
use App\Models\User;
use App\Models\UserUnidadAcceso;
use App\Services\Reemplazos\AnulacionTransitoria;
use App\Services\Respaldos\EscriturasPeriodosTransitorios;
use App\Services\Respaldos\RespaldosAptosSolicitudTransitoria;
use App\Services\Respaldos\TransaccionPeriodosTransitorios;
use Database\Seeders\AnulacionesTransitoriasV3Seeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AnularSolicitudTransitoriaTest extends TestCase
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
        $this->actor->givePermissionTo(['reemplazos.crear', 'reemplazos.anular']);
        $this->unidad = UnidadOrganizacional::query()->where('activo', true)->firstOrFail();
        UserUnidadAcceso::query()->create(['user_id' => $this->actor->id, 'unidad_organizacional_id' => $this->unidad->id, 'alcance' => AlcanceAccesoOperativo::SOLO_UNIDAD, 'vigente_desde' => today()->subDay(), 'created_by' => $this->actor->id]);
        $this->funcionario = $this->persona('89000001-1');
        $this->candidato = $this->persona('89000002-2');
    }

    public function test_operator_cancels_draft_without_fictitious_commitments(): void
    {
        [$tramite] = $this->caso(false);
        $this->estado($tramite, 'BORRADOR');
        $this->anular($tramite);
        $this->assertSame('ANULADA', $tramite->fresh()->estadoTramite->codigo);
        $this->assertNotNull($tramite->fresh()->finalized_at);
        $this->assertDatabaseCount('respaldo_afectaciones', 0);
        $this->assertDatabaseCount('reserva_persona_periodos', 0);
        $this->assertSame('Motivo ficticio', $tramite->historial()->where('action_code', 'ANULAR_SOLICITUD')->sole()->observation);
    }

    public function test_operator_cannot_cancel_after_submission_even_when_returned_to_draft(): void
    {
        [$tramite] = $this->caso(false);
        $this->envio($tramite);
        $this->estado($tramite, 'BORRADOR');
        $this->expectException(AuthorizationException::class);
        $this->anular($tramite);
    }

    public function test_gp_cancels_sent_request_before_approval_without_reservations(): void
    {
        [$tramite] = $this->caso(false);
        $this->envio($tramite);
        $this->gp();
        $this->anular($tramite);
        $this->assertDatabaseCount('respaldo_afectaciones', 0);
        $this->assertDatabaseCount('reserva_persona_periodos', 0);
    }

    public function test_approved_commitments_are_released_atomically_with_audit_and_petition(): void
    {
        [$tramite, $respaldo] = $this->caso();
        $envio = $this->envio($tramite);
        $peticion = app(SolicitarAnulacionTransitoriaAction::class)->execute($tramite, $this->actor, 'Petición ficticia');
        $this->gp();
        $this->aprobar($tramite, $respaldo);
        $afectacion = $tramite->solicitudContrato->afectacionesRespaldo()->sole();
        $reserva = $afectacion->reservas()->sole();
        $this->anular($tramite);
        $evento = $tramite->historial()->where('action_code', 'ANULAR_SOLICITUD')->sole();
        $this->assertSame([$afectacion->id], $evento->metadata['afectaciones_liberadas']);
        $this->assertSame([$reserva->id], $evento->metadata['reservas_liberadas']);
        $this->assertSame([$peticion->id], $evento->metadata['peticiones_historial_ids']);
        $this->assertSame($envio->id, $evento->metadata['primer_envio_historial_id']);
        $this->assertSame($this->actor->id, $afectacion->fresh()->liberado_por);
        $this->assertSame('Motivo ficticio', $reserva->fresh()->motivo_liberacion);
        $this->assertEquals($afectacion->fresh()->liberado_at, $reserva->fresh()->liberado_at);
        $this->assertSame('PENDIENTE', $peticion->fresh()->metadata['resultado']);
        $this->assertDatabaseCount('respaldo_afectaciones', 1);
        $this->assertDatabaseCount('reserva_persona_periodos', 1);
    }

    public function test_backing_is_reused_as_new_historical_affectation_and_active_duplicates_rejected(): void
    {
        [$primero, $respaldo] = $this->caso(false);
        $this->envio($primero);
        $this->gp();
        $this->aprobar($primero, $respaldo);
        $anterior = $primero->solicitudContrato->afectacionesRespaldo()->sole();
        $this->anular($primero);
        [$segundo] = $this->caso(false, $this->persona('89000007-7'));
        $segundo->reemplazo()->update(['funcionario_id' => $this->funcionario->id]);
        $this->aprobar($segundo, $respaldo);
        $nuevo = $segundo->solicitudContrato->afectacionesRespaldo()->sole();
        $this->assertNotSame($anterior->id, $nuevo->id);
        $this->assertNotNull($anterior->fresh()->liberado_at);
        $this->assertNull($nuevo->liberado_at);
        [$tercero] = $this->caso(false, $this->persona('89000008-8'));
        $tercero->reemplazo()->update(['funcionario_id' => $this->funcionario->id]);
        try {
            $this->aprobar($tercero, $respaldo);
            $this->fail('No admite compromiso simultáneo.');
        } catch (ValidationException) {
            $this->assertSame(1, RespaldoAfectacion::query()->where('respaldo_id', $respaldo->id)->whereNull('liberado_at')->count());
        }
    }

    public function test_failure_after_release_rolls_back_everything(): void
    {
        [$tramite, $respaldo] = $this->caso();
        $this->envio($tramite);
        $this->gp();
        $this->aprobar($tramite, $respaldo);
        $motor = \Mockery::mock(TransicionarTramite::class);
        $motor->shouldReceive('execute')->once()->andReturnUsing(function () {
            $this->assertSame(0, ReservaPersonaPeriodo::query()->whereNull('liberado_at')->count());
            $this->assertSame(0, RespaldoAfectacion::query()->whereNull('liberado_at')->count());
            throw new \RuntimeException('Fallo ficticio después de liberar.');
        });
        $accion = new AnularSolicitudTransitoriaAction(app(TransaccionPeriodosTransitorios::class), $motor);
        try {
            $accion->execute($tramite, $this->actor, 'Motivo ficticio');
            $this->fail('Debe revertir.');
        } catch (\RuntimeException) {
            $this->assertSame('LISTA_GENERAR_DOCUMENTO', $tramite->fresh()->estadoTramite->codigo);
            $this->assertSame(1, RespaldoAfectacion::query()->whereNull('liberado_at')->count());
            $this->assertSame(1, ReservaPersonaPeriodo::query()->whereNull('liberado_at')->count());
            $this->assertSame(0, $tramite->historial()->where('action_code', 'ANULAR_SOLICITUD')->count());
        }
    }

    public function test_direct_transition_cannot_bypass_the_transactional_action(): void
    {
        [$tramite] = $this->caso(false);
        $this->estado($tramite, 'BORRADOR');
        $this->expectException(ValidationException::class);
        app(TransicionarTramite::class)->execute($tramite, 'ANULAR_SOLICITUD', $this->actor, 'Motivo');
    }

    public function test_double_annulment_and_terminal_outgoing_transition_are_rejected(): void
    {
        [$tramite] = $this->caso(false);
        $this->estado($tramite, 'BORRADOR');
        $this->anular($tramite);
        try {
            $this->anular($tramite);
            $this->fail('No admite segunda anulación.');
        } catch (ValidationException) {
            $this->assertSame(1, $tramite->historial()->where('action_code', 'ANULAR_SOLICITUD')->count());
        }
        TransicionEstado::query()->create(['tipo_tramite_id' => $tramite->tipo_tramite_id, 'estado_origen_id' => $tramite->fresh()->estado_tramite_id, 'estado_destino_id' => $this->estadoRevision()->id, 'codigo_accion' => 'REABRIR_FICTICIO', 'activo' => true]);
        $this->expectException(ValidationException::class);
        app(TransicionarTramite::class)->execute($tramite, 'REABRIR_FICTICIO', $this->actor);
    }

    public function test_http_annul_and_request_views_with_scope_and_reason_validation(): void
    {
        [$tramite] = $this->caso(false);
        $this->estado($tramite, 'BORRADOR');
        $this->actor->givePermissionTo('tramites.ver_unidades');
        $this->actingAs($this->actor)->get(route('reemplazos.show', $tramite))->assertOk()->assertSee('Anular solicitud');
        $this->post(route('reemplazos.annul', $tramite), [])->assertSessionHasErrors('motivo');
        $this->envio($tramite);
        $this->get(route('reemplazos.show', $tramite))->assertOk()->assertSee('Solicitar anulación');
        $this->post(route('reemplazos.annul', $tramite), ['motivo' => 'Motivo'])->assertForbidden();
        $this->post(route('reemplazos.request-annulment', $tramite), ['motivo' => 'Petición visible'])->assertRedirect();
        $this->gp();
        $this->get(route('gestion-personas.reemplazos.show', $tramite))->assertOk()->assertSee('Petición visible');
        $this->post(route('reemplazos.annul', $tramite), ['motivo' => 'Resolución visible'])->assertRedirect();
        $this->get(route('gestion-personas.reemplazos.show', $tramite))->assertOk()->assertSee('Resolución visible')->assertDontSee('Anular solicitud</button>', false);
    }

    public function test_unauthorized_out_of_scope_inactive_and_permanent_are_rejected(): void
    {
        [$tramite] = $this->caso(false);
        $this->estado($tramite, 'BORRADOR');
        foreach (['permission', 'scope', 'inactive'] as $caso) {
            $actor = User::factory()->create(['active' => $caso !== 'inactive']);
            if ($caso !== 'permission') {
                $actor->givePermissionTo(['reemplazos.crear', 'reemplazos.anular']);
            }
            try {
                app(AnularSolicitudTransitoriaAction::class)->execute($tramite, $actor, 'Motivo');
                $this->fail('No autorizado.');
            } catch (AuthorizationException) {
                $this->assertSame('BORRADOR', $tramite->fresh()->estadoTramite->codigo);
            }
        }
        $tramite->solicitudContrato()->update(['modalidad' => ModalidadSolicitudContrato::PERMANENTE]);
        $this->expectException(ValidationException::class);
        $this->anular($tramite);
    }

    public function test_v2_route_and_history_are_preserved(): void
    {
        [$v3] = $this->caso(false);
        $v2 = Tramite::query()->create(['public_id' => (string) Str::ulid(), 'codigo' => 'LEGADO-FICTICIO', 'tipo_tramite_id' => $v3->tipo_tramite_id, 'estado_tramite_id' => $v3->estado_tramite_id, 'unidad_organizacional_id' => $this->unidad->id, 'created_by' => $this->actor->id]);
        $this->actingAs($this->actor)->post(route('reemplazos.annul', $v2), ['motivo' => 'Motivo'])->assertSessionHasErrors('solicitud');
        $this->assertSame(0, $v2->historial()->count());
        $this->assertSame($v3->estado_tramite_id, $v2->fresh()->estado_tramite_id);
    }

    private function gp(): void
    {
        $this->actor->givePermissionTo(['reemplazos.anular_enviadas', 'reemplazos.revisar', 'reemplazos.alcance_global']);
    }

    public function test_http_rejects_document_in_ready_state_without_releasing_commitments(): void
    {
        [$tramite, $respaldo] = $this->caso();
        $this->envio($tramite);
        $this->gp();
        $this->aprobar($tramite, $respaldo);
        // Inconsistencia deliberada: documento existente en una etapa previa.
        $plantilla = DocumentoPlantilla::query()->firstOrFail();
        $adjunto = TramiteAdjunto::query()->create([
            'tramite_id' => $tramite->id, 'tipo_documento_id' => $plantilla->tipo_documento_id,
            'original_name' => 'documento-ficticio.pdf', 'stored_name' => Str::ulid().'.pdf',
            'storage_path' => 'tests/documento-ficticio.pdf', 'mime_type' => 'application/pdf',
            'size_bytes' => 10, 'sha256' => hash('sha256', 'ficticio'),
            'uploaded_by' => $this->actor->id, 'version' => 1, 'status' => 'ACTIVO',
        ]);
        $documento = DocumentoGenerado::query()->create([
            'tramite_id' => $tramite->id, 'documento_plantilla_id' => $plantilla->id,
            'tipo_documento_id' => $plantilla->tipo_documento_id, 'adjunto_id' => $adjunto->id,
            'version' => 1, 'generated_by' => $this->actor->id, 'generated_at' => now(),
            'status' => 'VIGENTE', 'metadata' => ['prueba' => 'ficticia'],
        ]);
        $snapshot = $documento->fresh()->getAttributes();
        $historial = $tramite->historial()->count();
        $this->actingAs($this->actor)->post(route('reemplazos.annul', $tramite), ['motivo' => 'Motivo'])->assertSessionHasErrors('solicitud');
        $this->assertSame('LISTA_GENERAR_DOCUMENTO', $tramite->fresh()->estadoTramite->codigo);
        $this->assertSame($snapshot, $documento->fresh()->getAttributes());
        $this->assertSame($historial, $tramite->historial()->count());
        $this->assertNull($tramite->solicitudContrato->afectacionesRespaldo()->sole()->liberado_at);
        $this->assertNull($tramite->solicitudContrato->reservasPersona()->sole()->liberado_at);
    }

    public function test_seeder_preserves_custom_roles_permissions_and_unrelated_transitions(): void
    {
        $permiso = Permission::findOrCreate('auditoria.ficticia', 'web');
        $rol = Role::findOrCreate('Rol ficticio', 'web');
        $rol->givePermissionTo($permiso);
        Role::findByName('Jefatura')->givePermissionTo($permiso);
        $transiciones = TransicionEstado::query()->where('codigo_accion', '!=', 'ANULAR_SOLICITUD')->orderBy('id')->get()->toArray();
        $this->seed(AnulacionesTransitoriasV3Seeder::class);
        $this->seed(AnulacionesTransitoriasV3Seeder::class);
        $this->assertTrue($rol->fresh()->hasPermissionTo('auditoria.ficticia'));
        $this->assertTrue(Role::findByName('Jefatura')->hasPermissionTo('auditoria.ficticia'));
        $this->assertSame($transiciones, TransicionEstado::query()->where('codigo_accion', '!=', 'ANULAR_SOLICITUD')->orderBy('id')->get()->toArray());
    }

    public function test_seeder_conflict_rolls_back_and_preserves_existing_configuration(): void
    {
        $transicion = TransicionEstado::query()->where('codigo_accion', 'ANULAR_SOLICITUD')->firstOrFail();
        $transicion->update(['activo' => false]);
        $rol = Role::findByName('Jefatura');
        $rol->revokePermissionTo('reemplazos.anular');
        try {
            $this->seed(AnulacionesTransitoriasV3Seeder::class);
            $this->fail('Debe rechazar el conflicto sin modificaciones parciales.');
        } catch (\LogicException) {
            $this->assertFalse($transicion->fresh()->activo);
            $this->assertFalse($rol->fresh()->hasPermissionTo('reemplazos.anular'));
        }
    }

    public function test_missing_catalog_prerequisite_does_not_leave_partial_installation(): void
    {
        $estado = EstadoTramite::query()->where('codigo', 'LISTA_GENERAR_DOCUMENTO')->sole();
        TransicionEstado::query()->where('estado_origen_id', $estado->id)->orWhere('estado_destino_id', $estado->id)->delete();
        $estado->delete();
        $rol = Role::findByName('Jefatura');
        $rol->revokePermissionTo('reemplazos.anular');
        $snapshot = TransicionEstado::query()->orderBy('id')->get()->toArray();
        try {
            $this->seed(AnulacionesTransitoriasV3Seeder::class);
            $this->fail('Debe rechazar el catálogo incompleto.');
        } catch (ModelNotFoundException) {
            $this->assertFalse($rol->fresh()->hasPermissionTo('reemplazos.anular'));
            $this->assertSame($snapshot, TransicionEstado::query()->orderBy('id')->get()->toArray());
        }
    }

    public function test_sent_annulment_requires_permission_and_scope_despite_global_visibility(): void
    {
        [$tramite] = $this->caso(false);
        $this->envio($tramite);
        $this->actor->givePermissionTo('tramites.ver_todos');
        $this->actingAs($this->actor)->post(route('reemplazos.annul', $tramite), ['motivo' => 'Motivo'])->assertForbidden();
        $sinAlcance = User::factory()->create(['active' => true]);
        $sinAlcance->givePermissionTo(['reemplazos.anular', 'reemplazos.anular_enviadas', 'tramites.ver_todos']);
        $this->actingAs($sinAlcance)->post(route('reemplazos.annul', $tramite), ['motivo' => 'Motivo'])->assertForbidden();
        $this->assertSame(0, $tramite->historial()->where('action_code', 'ANULAR_SOLICITUD')->count());
    }

    public function test_gp_can_cancel_all_admitted_sent_states_and_catalog_is_idempotent(): void
    {
        $this->gp();
        foreach (AnulacionTransitoria::ESTADOS as $indice => $codigo) {
            [$tramite] = $this->caso(false, $this->persona('8900010'.$indice.'-1'));
            $this->estado($tramite, $codigo);
            $this->envio($tramite);
            $this->anular($tramite);
            $this->assertSame('ANULADA', $tramite->fresh()->estadoTramite->codigo);
        }
        $this->seed(AnulacionesTransitoriasV3Seeder::class);
        $this->seed(AnulacionesTransitoriasV3Seeder::class);
        $this->assertSame(1, EstadoTramite::query()->where('codigo', 'ANULADA')->count());
        $this->assertSame(5, TransicionEstado::query()->where('codigo_accion', 'ANULAR_SOLICITUD')->count());
        $this->assertSame(0, TransicionEstado::query()->where('estado_origen_id', EstadoTramite::query()->where('codigo', 'ANULADA')->sole()->id)->count());
    }

    public function test_public_reuse_selector_and_approval_accept_released_backing(): void
    {
        [$primero, $respaldo] = $this->caso(false);
        $this->envio($primero);
        $this->gp();
        $this->aprobar($primero, $respaldo);
        $this->anular($primero);
        [$segundo] = $this->caso(false, $this->persona('89000009-9'));
        $segundo->reemplazo()->update(['funcionario_id' => $this->funcionario->id]);
        $segundo->unsetRelations();
        $aptos = app(RespaldosAptosSolicitudTransitoria::class)->obtener($segundo->solicitudContrato, $segundo->reemplazo);
        $this->assertContains($respaldo->id, $aptos->pluck('id')->all());
        $this->actingAs($this->actor)->put(route('gestion-personas.reemplazos.approve', $segundo), ['grado_eus' => 12, 'clasificacion_area_id' => ClasificacionArea::query()->firstOrFail()->id, 'cumple_normativa' => true, 'respaldo_version_id' => $respaldo->versionActual->id])->assertRedirect();
        $this->assertSame('LISTA_GENERAR_DOCUMENTO', $segundo->fresh()->estadoTramite->codigo);
    }

    private function estado(Tramite $tramite, string $codigo): void
    {
        $tramite->update(['estado_tramite_id' => EstadoTramite::query()->where('codigo', $codigo)->sole()->id]);
        $tramite->unsetRelations();
    }

    private function anular(Tramite $tramite): void
    {
        app(AnularSolicitudTransitoriaAction::class)->execute($tramite, $this->actor, 'Motivo ficticio');
    }

    private function aprobar(Tramite $tramite, RespaldoTransitorio $respaldo): void
    {
        app(AprobarAntecedentesTransitoriosAction::class)->execute($tramite, $respaldo, $respaldo->versionActual->id, ['grado_eus' => 12, 'clasificacion_area_id' => ClasificacionArea::query()->firstOrFail()->id, 'cumple_normativa' => true], $this->actor);
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
