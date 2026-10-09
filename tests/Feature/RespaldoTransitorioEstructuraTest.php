<?php

namespace Tests\Feature;

use App\Enums\ContextoAutoridadInstitucional;
use App\Enums\ModalidadSolicitudContrato;
use App\Models\EstadoTramite;
use App\Models\Persona;
use App\Models\ReservaPersonaPeriodo;
use App\Models\RespaldoAfectacion;
use App\Models\RespaldoTransitorio;
use App\Models\RespaldoTransitorioVersion;
use App\Models\SolicitudContrato;
use App\Models\TipoTramite;
use App\Models\Tramite;
use App\Models\UnidadOrganizacional;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use LogicException;
use Tests\TestCase;

class RespaldoTransitorioEstructuraTest extends TestCase
{
    use RefreshDatabase;

    private User $actor;

    private UnidadOrganizacional $unidad;

    private Persona $funcionario;

    private Persona $propuesta;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->actor = User::factory()->create();
        $this->unidad = UnidadOrganizacional::query()->firstOrFail();
        $this->funcionario = Persona::query()->create(['rut' => '89000001-1', 'nombres' => 'Funcionario Ficticio']);
        $this->propuesta = Persona::query()->create(['rut' => '89000002-2', 'nombres' => 'Persona Ficticia']);
    }

    public function test_respaldo_has_generated_unique_id_optional_external_reference_and_explicit_origin(): void
    {
        $solicitud = $this->solicitud();
        $primero = $this->respaldo($solicitud);
        $segundo = $this->respaldo($solicitud, ['referencia_externa' => 'ACTO-EXTERNO-123']);

        $this->assertTrue(Str::isUlid($primero->public_id));
        $this->assertNotSame($primero->public_id, $segundo->public_id);
        $this->assertNull($primero->versionActual->referencia_externa);
        $this->assertSame('ACTO-EXTERNO-123', $segundo->versionActual->referencia_externa);
        $this->assertTrue($primero->versionActual->funcionarioOrigen->is($this->funcionario));
        $this->assertTrue($primero->versionActual->unidadOrigen->is($this->unidad));
        $this->assertTrue($primero->solicitudOrigen->is($solicitud));
        $this->assertCount(2, $solicitud->respaldosRegistrados);
        $this->assertDatabaseCount('respaldo_transitorio_versiones', 2);
    }

    public function test_foreign_keys_and_version_membership_reject_invalid_references(): void
    {
        $primero = $this->respaldo($this->solicitud());
        $segundo = $this->respaldo($this->solicitud());

        try {
            $this->version($primero, ['version' => 2, 'motivo_rectificacion' => 'Corrección ficticia', 'funcionario_origen_id' => 999999]);
            $this->fail('La FK de Persona debe rechazar un funcionario inexistente.');
        } catch (QueryException) {
            $this->assertDatabaseCount('respaldo_transitorio_versiones', 2);
        }

        try {
            $this->version($primero, ['version' => 2, 'motivo_rectificacion' => 'Corrección ficticia', 'unidad_origen_id' => 999999]);
            $this->fail('La FK de Unidad debe rechazar una unidad inexistente.');
        } catch (QueryException) {
            $this->assertDatabaseCount('respaldo_transitorio_versiones', 2);
        }

        $this->expectException(QueryException::class);
        $this->afectacion($primero, $this->solicitud(), ['respaldo_version_id' => $segundo->versionActual->id]);
    }

    public function test_released_affectation_remains_and_same_backing_can_be_reused(): void
    {
        $original = $this->solicitud();
        $respaldo = $this->respaldo($original);
        $primera = $this->afectacion($respaldo, $original);
        $primera->update(['liberado_at' => now()->addMinute(), 'liberado_por' => $this->actor->id, 'motivo_liberacion' => 'Anulación previa al documento']);
        $segundaSolicitud = $this->solicitud();
        $segunda = $this->afectacion($respaldo, $segundaSolicitud);

        $this->assertSame('LIBERADA', $primera->fresh()->estado);
        $this->assertSame('VIGENTE', $segunda->estado);
        $this->assertCount(2, $respaldo->afectaciones);
        $this->assertTrue($segundaSolicitud->afectacionesRespaldo->first()->is($segunda));
        $this->assertSame($this->actor->id, $primera->fresh()->liberado_por);
    }

    public function test_database_rejects_two_active_affectations_for_backing_or_request(): void
    {
        $solicitud = $this->solicitud();
        $respaldo = $this->respaldo($solicitud);
        $this->afectacion($respaldo, $solicitud);

        try {
            $this->afectacion($respaldo, $this->solicitud());
            $this->fail('El respaldo ya está comprometido.');
        } catch (QueryException) {
            $this->assertDatabaseCount('respaldo_afectaciones', 1);
        }

        $this->expectException(QueryException::class);
        $this->afectacion($this->respaldo($solicitud), $solicitud);
    }

    public function test_reservations_keep_released_history_and_only_one_active_per_affectation(): void
    {
        $solicitud = $this->solicitud();
        $afectacion = $this->afectacion($this->respaldo($solicitud), $solicitud);
        $primera = $this->reserva($afectacion);
        $primera->update(['liberado_at' => now()->addMinute(), 'liberado_por' => $this->actor->id, 'motivo_liberacion' => 'Cambio de persona']);
        $segunda = $this->reserva($afectacion, ['persona_id' => $this->funcionario->id]);

        $this->assertSame('LIBERADA', $primera->fresh()->estado);
        $this->assertSame('VIGENTE', $segunda->estado);
        $this->assertCount(2, $afectacion->reservas);
        $this->assertCount(2, $solicitud->reservasPersona);
        $this->assertTrue($this->propuesta->reservasPropuestas->first()->is($primera));

        $this->expectException(QueryException::class);
        $this->reserva($afectacion);
    }

    public function test_inverted_dates_and_incomplete_release_are_rejected(): void
    {
        $solicitud = $this->solicitud();
        $respaldo = $this->respaldo($solicitud);

        try {
            $this->version($respaldo, ['fecha_desde' => '2026-11-01', 'fecha_hasta' => '2026-10-31']);
            $this->fail('Las fechas invertidas del respaldo deben rechazarse.');
        } catch (LogicException) {
            $this->assertDatabaseCount('respaldo_transitorio_versiones', 1);
        }

        $afectacion = $this->afectacion($respaldo, $solicitud);
        try {
            $afectacion->update(['liberado_at' => now()->addMinute()]);
            $this->fail('Una liberación incompleta debe rechazarse.');
        } catch (LogicException) {
            $this->assertSame('VIGENTE', $afectacion->fresh()->estado);
        }

        $this->expectException(LogicException::class);
        $this->reserva($afectacion, ['fecha_desde' => '2026-11-01', 'fecha_hasta' => '2026-10-31']);
    }

    public function test_versions_preserve_original_values_and_affectation_snapshot_reference(): void
    {
        $solicitud = $this->solicitud();
        $respaldo = $this->respaldo($solicitud);
        $primera = $respaldo->versionActual;
        $afectacion = $this->afectacion($respaldo, $solicitud);
        $segunda = $this->version($respaldo, ['version' => 2, 'fecha_hasta' => '2026-10-25', 'motivo_rectificacion' => 'Antecedente corregido']);

        $this->assertSame('2026-10-31', $primera->fecha_hasta->toDateString());
        $this->assertSame('2026-10-25', $respaldo->fresh()->versionActual->fecha_hasta->toDateString());
        $this->assertTrue($afectacion->fresh()->versionRespaldo->is($primera));
        $this->assertTrue($respaldo->versiones->last()->is($segunda));

        $this->expectException(LogicException::class);
        $primera->update(['motivo' => 'Sobrescritura']);
    }

    public function test_v3_request_without_candidate_and_v2_history_remain_untouched(): void
    {
        $solicitud = $this->solicitud();
        $respaldo = $this->respaldo($solicitud);
        $this->assertNull($solicitud->tramite->reemplazo);
        $this->assertCount(0, $solicitud->reservasPersona);
        $this->assertCount(0, $respaldo->afectaciones);

        $v2 = $this->tramite();
        $this->assertNull($v2->solicitudContrato);
        $this->assertDatabaseCount('respaldos_transitorios', 1);
        $this->assertTrue(Schema::hasTable('respaldo_afectaciones'));
        $this->assertTrue(Schema::hasTable('reserva_persona_periodos'));
    }

    public function test_transitory_records_cannot_be_created_for_permanent_requests_through_models(): void
    {
        $permanente = $this->solicitud(ModalidadSolicitudContrato::PERMANENTE);
        try {
            $this->respaldo($permanente);
            $this->fail('El respaldo transitorio debe rechazar modalidad permanente.');
        } catch (LogicException) {
            $this->assertDatabaseCount('respaldos_transitorios', 0);
        }

        $transitoria = $this->solicitud();
        $respaldo = $this->respaldo($transitoria);
        $this->expectException(LogicException::class);
        $this->afectacion($respaldo, $permanente);
    }

    private function tramite(): Tramite
    {
        $tipo = TipoTramite::query()->where('codigo', 'REEMPLAZO')->firstOrFail();
        $estado = EstadoTramite::query()->where('tipo_tramite_id', $tipo->id)->where('codigo', 'BORRADOR')->firstOrFail();

        return Tramite::query()->create([
            'public_id' => (string) Str::ulid(),
            'codigo' => 'TEST-'.Str::upper(Str::random(12)),
            'tipo_tramite_id' => $tipo->id,
            'estado_tramite_id' => $estado->id,
            'unidad_organizacional_id' => $this->unidad->id,
            'created_by' => $this->actor->id,
        ]);
    }

    private function solicitud(ModalidadSolicitudContrato $modalidad = ModalidadSolicitudContrato::TRANSITORIA): SolicitudContrato
    {
        return SolicitudContrato::query()->forceCreate([
            'tramite_id' => $this->tramite()->id,
            'modalidad' => $modalidad,
            'unidad_solicitante_id' => $this->unidad->id,
            'autoridad_persona_id' => $this->funcionario->id,
            'autoridad_contexto' => ContextoAutoridadInstitucional::REGISTRO_SOLICITUD_CONTRATO,
            'autoridad_resuelta_at' => now(),
            'unidad_origen_id' => $this->unidad->id,
            'unidad_destino_id' => $this->unidad->id,
        ]);
    }

    private function respaldo(SolicitudContrato $solicitud, array $version = []): RespaldoTransitorio
    {
        $respaldo = RespaldoTransitorio::query()->create(['solicitud_origen_id' => $solicitud->id, 'created_by' => $this->actor->id]);
        $this->version($respaldo, $version);

        return $respaldo;
    }

    private function version(RespaldoTransitorio $respaldo, array $cambios = []): RespaldoTransitorioVersion
    {
        return $respaldo->versiones()->create([...[
            'version' => 1,
            'funcionario_origen_id' => $this->funcionario->id,
            'unidad_origen_id' => $this->unidad->id,
            'motivo' => 'Permiso transitorio ficticio',
            'fecha_desde' => '2026-10-01',
            'fecha_hasta' => '2026-10-31',
            'registrado_por' => $this->actor->id,
        ], ...$cambios]);
    }

    private function afectacion(RespaldoTransitorio $respaldo, SolicitudContrato $solicitud, array $cambios = []): RespaldoAfectacion
    {
        return RespaldoAfectacion::query()->create([...[
            'respaldo_id' => $respaldo->id,
            'respaldo_version_id' => $respaldo->versionActual->id,
            'solicitud_contrato_id' => $solicitud->id,
            'comprometido_at' => now(),
            'comprometido_por' => $this->actor->id,
        ], ...$cambios]);
    }

    private function reserva(RespaldoAfectacion $afectacion, array $cambios = []): ReservaPersonaPeriodo
    {
        return ReservaPersonaPeriodo::query()->create([...[
            'afectacion_id' => $afectacion->id,
            'persona_id' => $this->propuesta->id,
            'fecha_desde' => '2026-10-01',
            'fecha_hasta' => '2026-10-20',
            'reservado_at' => now(),
            'reservado_por' => $this->actor->id,
        ], ...$cambios]);
    }
}
