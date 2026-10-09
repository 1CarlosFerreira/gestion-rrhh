<?php

namespace Tests\Feature;

use App\Enums\ContextoAutoridadInstitucional;
use App\Enums\ModalidadSolicitudContrato;
use App\Models\EstadoTramite;
use App\Models\Persona;
use App\Models\RespaldoAfectacion;
use App\Models\SolicitudContrato;
use App\Models\TipoTramite;
use App\Models\Tramite;
use App\Models\UnidadOrganizacional;
use App\Models\User;
use App\Services\Respaldos\EscriturasPeriodosTransitorios;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class PeriodosMysqlConcurrenciaTest extends TestCase
{
    private User $actor;

    private UnidadOrganizacional $unidad;

    private Persona $funcionario;

    protected function setUp(): void
    {
        parent::setUp();
        $database = getenv('RRHH_MYSQL_TEST_DATABASE');
        if (! is_string($database) || ! str_starts_with($database, 'gestion_rrhh_3b_test_')) {
            $this->markTestSkipped('Requiere RRHH_MYSQL_TEST_DATABASE con una base MySQL 8.4 aislada y vacía.');
        }

        $config = config('database.connections.mysql');
        $config['database'] = $database;
        config(['database.connections.mysql_3b' => $config, 'database.default' => 'mysql_3b']);
        DB::purge('mysql_3b');
        $this->assertSame('8.4', substr(DB::connection()->selectOne('select version() as version')->version, 0, 3));
        $this->assertSame([], DB::select('show tables'), 'La base de concurrencia debe iniciar vacía.');

        Artisan::call('migrate', ['--database' => 'mysql_3b', '--force' => true]);
        $this->seed();
        $this->actor = User::factory()->create();
        $this->unidad = UnidadOrganizacional::query()->firstOrFail();
        $this->funcionario = $this->persona('87000001-1');
    }

    public function test_two_independent_mysql_connections_serialize_backings_and_reservations(): void
    {
        $primera = $this->solicitud();
        $segunda = $this->solicitud();

        $resultados = $this->competir($this->funcionario->id, [
            ['respaldo', $primera->id, $this->actor->id, $this->funcionario->id, $this->unidad->id, '2026-10-01', '2026-10-20'],
            ['respaldo', $segunda->id, $this->actor->id, $this->funcionario->id, $this->unidad->id, '2026-10-15', '2026-10-25'],
        ]);
        $this->assertSame(['CONFLICT', 'OK'], $resultados);
        $this->assertDatabaseCount('respaldos_transitorios', 1);
        $this->assertDatabaseCount('respaldo_transitorio_versiones', 1);

        $motor = app(EscriturasPeriodosTransitorios::class);
        $motor->registrarRespaldo($this->solicitud(), $this->actor, ['funcionario_origen_id' => $this->funcionario->id, 'unidad_origen_id' => $this->unidad->id, 'motivo' => 'Período consecutivo ficticio', 'fecha_desde' => '2026-10-26', 'fecha_hasta' => '2026-10-31']);

        $origenA = $this->persona('87000002-2');
        $origenB = $this->persona('87000003-3');
        $afectacionA = $this->afectacion($origenA);
        $afectacionB = $this->afectacion($origenB);
        $candidato = $this->persona('87000004-4');
        $resultados = $this->competir($candidato->id, [
            ['reserva', $afectacionA->id, $this->actor->id, $candidato->id, '2026-10-01', '2026-10-20'],
            ['reserva', $afectacionB->id, $this->actor->id, $candidato->id, '2026-10-15', '2026-10-25'],
        ]);
        $this->assertSame(['CONFLICT', 'OK'], $resultados);
        $this->assertDatabaseCount('reserva_persona_periodos', 1);

        $origenC = $this->persona('87000005-5');
        $afectacionC = $this->afectacion($origenC);
        $motor->registrarReserva($afectacionC, $this->actor, $candidato->id, '2026-10-26', '2026-10-30');
        $this->assertDatabaseCount('reserva_persona_periodos', 2);
    }

    private function competir(int $personaId, array $acciones): array
    {
        $conexion = DB::connection();
        $conexion->beginTransaction();
        Persona::query()->lockForUpdate()->findOrFail($personaId);
        $procesos = [];

        try {
            foreach ($acciones as $accion) {
                $comando = ['php', base_path('tests/Support/periodos_mysql_worker.php'), getenv('RRHH_MYSQL_TEST_DATABASE'), ...$accion];
                $proceso = proc_open($comando, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path());
                $this->assertIsResource($proceso);
                $this->assertSame('START', trim(fgets($pipes[1])));
                $procesos[] = [$proceso, $pipes];
            }

            usleep(300000);
            foreach ($procesos as [$proceso]) {
                $this->assertTrue(proc_get_status($proceso)['running'], 'Ambas escrituras deben esperar el bloqueo de la persona.');
            }
        } finally {
            $conexion->commit();
        }

        $resultados = [];
        foreach ($procesos as [$proceso, $pipes]) {
            $salida = trim(stream_get_contents($pipes[1]));
            $error = trim(stream_get_contents($pipes[2]));
            fclose($pipes[1]);
            fclose($pipes[2]);
            $codigo = proc_close($proceso);
            $this->assertSame(0, $codigo, $error);
            $resultados[] = str_starts_with($salida, 'OK:') ? 'OK' : $salida;
        }
        sort($resultados);

        return $resultados;
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
            'codigo' => 'MYSQL-'.Str::upper(Str::random(12)),
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

    private function afectacion(Persona $funcionario): RespaldoAfectacion
    {
        $solicitud = $this->solicitud();
        $respaldo = app(EscriturasPeriodosTransitorios::class)->registrarRespaldo($solicitud, $this->actor, ['funcionario_origen_id' => $funcionario->id, 'unidad_origen_id' => $this->unidad->id, 'motivo' => 'Prueba concurrente ficticia', 'fecha_desde' => '2026-10-01', 'fecha_hasta' => '2026-10-31']);

        return RespaldoAfectacion::query()->create(['respaldo_id' => $respaldo->id, 'respaldo_version_id' => $respaldo->versionActual->id, 'solicitud_contrato_id' => $solicitud->id, 'comprometido_at' => now(), 'comprometido_por' => $this->actor->id]);
    }
}
