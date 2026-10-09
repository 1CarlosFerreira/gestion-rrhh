<?php

namespace Tests\Feature;

use App\Enums\ContextoAutoridadInstitucional;
use App\Enums\ModalidadSolicitudContrato;
use App\Models\EstadoTramite;
use App\Models\Persona;
use App\Models\RespaldoAfectacion;
use App\Models\RespaldoTransitorio;
use App\Models\SolicitudContrato;
use App\Models\TipoTramite;
use App\Models\Tramite;
use App\Models\UnidadOrganizacional;
use App\Models\User;
use App\Services\Respaldos\ConflictosPeriodosTransitorios;
use App\Services\Respaldos\EscriturasPeriodosTransitorios;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PeriodosTransitoriosMotorTest extends TestCase
{
    use RefreshDatabase;

    private EscriturasPeriodosTransitorios $motor;

    private User $actor;

    private UnidadOrganizacional $unidad;

    private Persona $funcionario;

    private Persona $candidato;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->motor = app(EscriturasPeriodosTransitorios::class);
        $this->actor = User::factory()->create();
        $this->unidad = UnidadOrganizacional::query()->firstOrFail();
        $this->funcionario = $this->persona('88000001-1');
        $this->candidato = $this->persona('88000002-2');
    }

    public function test_inclusive_overlap_matrix_and_consecutive_period(): void
    {
        $this->respaldo($this->funcionario, '2026-10-01', '2026-10-20');

        foreach ([
            ['2026-10-01', '2026-10-20'],
            ['2026-10-15', '2026-10-25'],
            ['2026-10-05', '2026-10-10'],
            ['2026-09-25', '2026-10-25'],
            ['2026-10-20', '2026-10-25'],
        ] as [$desde, $hasta]) {
            try {
                $this->respaldo($this->funcionario, $desde, $hasta);
                $this->fail("El período {$desde}–{$hasta} debió entrar en conflicto.");
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('fecha_desde', $exception->errors());
            }
        }

        $this->assertDatabaseCount('respaldos_transitorios', 1);
        $this->respaldo($this->funcionario, '2026-10-21', '2026-10-30');
        $this->assertDatabaseCount('respaldos_transitorios', 2);
    }

    public function test_inverted_or_invalid_calendar_dates_are_rejected_without_partial_write(): void
    {
        foreach ([['2026-10-31', '2026-10-01'], ['2026-02-30', '2026-03-01'], ['2026-10-01 12:00:00', '2026-10-02']] as [$desde, $hasta]) {
            try {
                $this->respaldo($this->funcionario, $desde, $hasta);
                $this->fail('La fecha inválida debió rechazarse.');
            } catch (ValidationException) {
                $this->assertDatabaseCount('respaldos_transitorios', 0);
            }
        }

        try {
            app(ConflictosPeriodosTransitorios::class)->reserva($this->candidato->id, '2026-02-30', '2026-03-01');
            $this->fail('La consulta informativa también debe rechazar fechas inválidas.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('fecha_desde', $exception->errors());
        }
    }

    public function test_backing_overlap_ignores_motive_and_request_but_not_origin_person(): void
    {
        $this->respaldo($this->funcionario, '2026-10-01', '2026-10-20', 'Permiso ficticio');

        try {
            $this->respaldo($this->funcionario, '2026-10-15', '2026-10-25', 'Licencia ficticia');
            $this->fail('Un motivo distinto no permite superposición.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('respaldos_transitorios', 1);
        }

        $otro = $this->persona('88000003-3');
        $this->respaldo($otro, '2026-10-15', '2026-10-25');
        $this->assertDatabaseCount('respaldos_transitorios', 2);
    }

    public function test_rectification_excludes_only_self_and_keeps_older_version(): void
    {
        $primero = $this->respaldo($this->funcionario, '2026-10-01', '2026-10-10');
        $this->respaldo($this->funcionario, '2026-10-20', '2026-10-30');

        $rectificada = $this->motor->rectificarPeriodoRespaldo($primero, $this->actor, '2026-10-01', '2026-10-19', 'Ajuste ficticio');
        $this->assertSame(2, $rectificada->version);
        $this->assertSame('2026-10-10', $primero->versiones()->firstOrFail()->fecha_hasta->toDateString());
        $this->assertSame('2026-10-19', $primero->fresh()->versionActual->fecha_hasta->toDateString());
        $this->assertTrue(app(ConflictosPeriodosTransitorios::class)->respaldo($this->funcionario->id, '2026-10-01', '2026-10-20', $primero->id));

        try {
            $this->motor->rectificarPeriodoRespaldo($primero, $this->actor, '2026-10-01', '2026-10-20', 'Intento en conflicto');
            $this->fail('La rectificación debe comprobar el segundo respaldo.');
        } catch (ValidationException) {
            $this->assertCount(2, $primero->versiones);
        }
    }

    public function test_released_affectation_does_not_remove_backing_from_overlap_query(): void
    {
        $respaldo = $this->respaldo($this->funcionario, '2026-10-01', '2026-10-20');
        $afectacion = $this->afectacion($respaldo);
        $afectacion->update(['liberado_at' => now()->addMinute(), 'liberado_por' => $this->actor->id, 'motivo_liberacion' => 'Anulación ficticia']);

        $this->expectException(ValidationException::class);
        $this->respaldo($this->funcionario, '2026-10-10', '2026-10-25');
    }

    public function test_active_backing_requires_coordinated_rectification(): void
    {
        $respaldo = $this->respaldo($this->funcionario, '2026-10-01', '2026-10-20');
        $this->afectacion($respaldo);

        $this->expectException(ValidationException::class);
        $this->motor->rectificarPeriodoRespaldo($respaldo, $this->actor, '2026-10-01', '2026-10-25', 'Ajuste pendiente');
    }

    public function test_reservations_conflict_inclusively_but_allow_consecutive_and_other_person(): void
    {
        $primera = $this->afectacion($this->respaldo($this->funcionario, '2026-10-01', '2026-10-20'));
        $this->motor->registrarReserva($primera, $this->actor, $this->candidato->id, '2026-10-01', '2026-10-20');
        $segundoOrigen = $this->persona('88000004-4');
        $segunda = $this->afectacion($this->respaldo($segundoOrigen, '2026-10-20', '2026-10-31'));

        try {
            $this->motor->registrarReserva($segunda, $this->actor, $this->candidato->id, '2026-10-20', '2026-10-25');
            $this->fail('El extremo común debe entrar en conflicto.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('reserva_persona_periodos', 1);
        }

        $this->motor->registrarReserva($segunda, $this->actor, $this->candidato->id, '2026-10-21', '2026-10-25');
        $tercerOrigen = $this->persona('88000005-5');
        $tercera = $this->afectacion($this->respaldo($tercerOrigen, '2026-10-01', '2026-10-31'));
        $otraPersona = $this->persona('88000006-6');
        $this->motor->registrarReserva($tercera, $this->actor, $otraPersona->id, '2026-10-10', '2026-10-25');
        $this->assertDatabaseCount('reserva_persona_periodos', 3);
    }

    public function test_released_reservation_does_not_block_and_missing_candidate_creates_none(): void
    {
        $primera = $this->afectacion($this->respaldo($this->funcionario, '2026-10-01', '2026-10-20'));
        $reserva = $this->motor->registrarReserva($primera, $this->actor, $this->candidato->id, '2026-10-01', '2026-10-20');
        $reserva->update(['liberado_at' => now()->addMinute(), 'liberado_por' => $this->actor->id, 'motivo_liberacion' => 'Liberación ficticia']);
        $segundoOrigen = $this->persona('88000007-7');
        $segunda = $this->afectacion($this->respaldo($segundoOrigen, '2026-10-01', '2026-10-20'));

        $this->assertDatabaseCount('reserva_persona_periodos', 1);
        $this->motor->registrarReserva($segunda, $this->actor, $this->candidato->id, '2026-10-01', '2026-10-20');
        $this->assertDatabaseCount('reserva_persona_periodos', 2);
        $this->assertSame('LIBERADA', $reserva->fresh()->estado);
    }

    public function test_reservation_must_fit_backing_and_informative_query_is_separate(): void
    {
        $afectacion = $this->afectacion($this->respaldo($this->funcionario, '2026-10-01', '2026-10-20'));
        $this->assertFalse(app(ConflictosPeriodosTransitorios::class)->reserva($this->candidato->id, '2026-10-01', '2026-10-20'));

        try {
            $this->motor->registrarReserva($afectacion, $this->actor, $this->candidato->id, '2026-10-01', '2026-10-21');
            $this->fail('La cobertura fuera del respaldo debe bloquearse.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('reserva_persona_periodos', 0);
        }

        $this->motor->registrarReserva($afectacion, $this->actor, $this->candidato->id, '2026-10-01', '2026-10-20');
        $this->assertTrue(app(ConflictosPeriodosTransitorios::class)->reserva($this->candidato->id, '2026-10-20', '2026-10-25'));
    }

    private function persona(string $rut): Persona
    {
        return Persona::query()->create(['rut' => $rut, 'nombres' => 'Persona ficticia']);
    }

    private function solicitud(): SolicitudContrato
    {
        $tipo = TipoTramite::query()->where('codigo', 'REEMPLAZO')->firstOrFail();
        $estado = EstadoTramite::query()->where('tipo_tramite_id', $tipo->id)->where('codigo', 'BORRADOR')->firstOrFail();
        $tramite = Tramite::query()->create([
            'public_id' => (string) Str::ulid(),
            'codigo' => 'PER-'.Str::upper(Str::random(12)),
            'tipo_tramite_id' => $tipo->id,
            'estado_tramite_id' => $estado->id,
            'unidad_organizacional_id' => $this->unidad->id,
            'created_by' => $this->actor->id,
        ]);

        return SolicitudContrato::query()->forceCreate([
            'tramite_id' => $tramite->id,
            'modalidad' => ModalidadSolicitudContrato::TRANSITORIA,
            'unidad_solicitante_id' => $this->unidad->id,
            'autoridad_persona_id' => $this->funcionario->id,
            'autoridad_contexto' => ContextoAutoridadInstitucional::REGISTRO_SOLICITUD_CONTRATO,
            'autoridad_resuelta_at' => now(),
            'unidad_origen_id' => $this->unidad->id,
            'unidad_destino_id' => $this->unidad->id,
        ]);
    }

    private function respaldo(Persona $funcionario, string $desde, string $hasta, string $motivo = 'Motivo ficticio'): RespaldoTransitorio
    {
        return $this->motor->registrarRespaldo($this->solicitud(), $this->actor, [
            'funcionario_origen_id' => $funcionario->id,
            'unidad_origen_id' => $this->unidad->id,
            'motivo' => $motivo,
            'fecha_desde' => $desde,
            'fecha_hasta' => $hasta,
        ]);
    }

    private function afectacion(RespaldoTransitorio $respaldo): RespaldoAfectacion
    {
        return RespaldoAfectacion::query()->create([
            'respaldo_id' => $respaldo->id,
            'respaldo_version_id' => $respaldo->versionActual->id,
            'solicitud_contrato_id' => $respaldo->solicitud_origen_id,
            'comprometido_at' => now(),
            'comprometido_por' => $this->actor->id,
        ]);
    }
}
