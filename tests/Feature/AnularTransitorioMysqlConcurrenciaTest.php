<?php

namespace Tests\Feature;

use App\Actions\Reemplazos\AprobarAntecedentesTransitoriosAction;
use App\Enums\ContextoAutoridadInstitucional;
use App\Enums\ModalidadSolicitudContrato;
use App\Models\ClasificacionArea;
use App\Models\EstadoTramite;
use App\Models\Persona;
use App\Models\ReservaPersonaPeriodo;
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
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class AnularTransitorioMysqlConcurrenciaTest extends TestCase
{
    private User $actor;

    private UnidadOrganizacional $unidad;

    private Persona $candidato;

    protected function setUp(): void
    {
        parent::setUp();
        $database = getenv('RRHH_MYSQL_3C2_TEST_DATABASE');
        if (! is_string($database) || ! str_starts_with($database, 'gestion_rrhh_3c2_test_')) {
            $this->markTestSkipped('Requiere una base MySQL 8.4 aislada y vacía con prefijo gestion_rrhh_3c2_test_.');
        }

        $config = config('database.connections.mysql');
        $config['database'] = $database;
        config(['database.connections.mysql_3c2' => $config, 'database.default' => 'mysql_3c2']);
        DB::purge('mysql_3c2');
        $this->assertSame('8.4', substr(DB::connection()->selectOne('select version() as version')->version, 0, 3));
        $this->assertSame([], DB::select('show tables'));
        Artisan::call('migrate', ['--database' => 'mysql_3c2', '--force' => true]);
        $this->seed();

        $this->actor = User::factory()->create(['active' => true]);
        $this->actor->givePermissionTo(['reemplazos.revisar', 'reemplazos.alcance_global', 'reemplazos.anular', 'reemplazos.anular_enviadas']);
        $this->unidad = UnidadOrganizacional::query()->firstOrFail();
        $this->candidato = $this->persona('86000001-1');
    }

    public function test_real_cancellation_approval_reuse_and_reservation_races(): void
    {
        [$primero, $respaldo] = $this->caso($this->persona('86000002-2'), $this->candidato);
        $this->aprobar($primero, $respaldo);
        $this->assertSame(['CONFLICT', 'OK'], $this->competir($this->candidato->id, [
            $this->operacion('annul', $primero, $respaldo),
            $this->operacion('annul', $primero, $respaldo),
        ]));
        $this->assertSame('ANULADA', $primero->fresh()->estadoTramite->codigo);
        $this->assertSame(1, $primero->historial()->where('action_code', 'ANULAR_SOLICITUD')->count());
        $this->assertSame(0, RespaldoAfectacion::query()->whereNull('liberado_at')->count());
        $this->assertSame(0, ReservaPersonaPeriodo::query()->whereNull('liberado_at')->count());

        [$segundo, $otro] = $this->caso($this->persona('86000003-3'), $this->candidato);
        $resultados = $this->competir($this->candidato->id, [$this->operacion('annul', $segundo, $otro), $this->operacion('approve', $segundo, $otro)]);
        $this->assertContains('OK', $resultados);
        $this->assertSame('ANULADA', $segundo->fresh()->estadoTramite->codigo);
        $this->assertSame(0, $segundo->solicitudContrato->afectacionesRespaldo()->whereNull('liberado_at')->count());
        $this->assertSame(0, $segundo->solicitudContrato->reservasPersona()->whereNull('reserva_persona_periodos.liberado_at')->count());

        // Una anulación compite con una nueva afectación de la misma identidad de respaldo.
        [$tercero, $reutilizable] = $this->caso($this->persona('86000004-4'), $this->candidato);
        $this->aprobar($tercero, $reutilizable);
        [$cuarto] = $this->caso($this->persona('86000005-5'), $this->persona('86000006-6'));
        $cuarto->reemplazo()->update(['funcionario_id' => $tercero->reemplazo->funcionario_id]);
        $resultados = $this->competir($tercero->reemplazo->funcionario_id, [$this->operacion('annul', $tercero, $reutilizable), $this->operacion('approve', $cuarto, $reutilizable)]);
        $this->assertContains('OK', $resultados);
        if ($cuarto->fresh()->estadoTramite->codigo === 'EN_REVISION') {
            $this->aprobar($cuarto, $reutilizable);
        }
        $this->assertSame(2, RespaldoAfectacion::query()->where('respaldo_id', $reutilizable->id)->count());
        $this->assertSame(1, RespaldoAfectacion::query()->where('respaldo_id', $reutilizable->id)->whereNull('liberado_at')->count());

        // Liberar la reserva compite con reservar esa persona en otra solicitud.
        [$quinto, $nuevo] = $this->caso($this->persona('86000007-7'), $this->candidato);
        $this->aprobar($quinto, $nuevo);
        [$sexto, $siguiente] = $this->caso($this->persona('86000008-8'), $this->candidato);
        $resultados = $this->competir($this->candidato->id, [$this->operacion('annul', $quinto, $nuevo), $this->operacion('approve', $sexto, $siguiente)]);
        $this->assertContains('OK', $resultados);
        if ($sexto->fresh()->estadoTramite->codigo === 'EN_REVISION') {
            $this->aprobar($sexto, $siguiente);
        }
        $this->assertSame(1, ReservaPersonaPeriodo::query()->where('persona_id', $this->candidato->id)->whereNull('liberado_at')->count());
        $this->assertNotNull($quinto->solicitudContrato->reservasPersona()->sole()->liberado_at);
    }

    private function operacion(string $modo, Tramite $tramite, RespaldoTransitorio $respaldo): array
    {
        return [$modo, $tramite->id, $respaldo->id, $respaldo->versionActual->id, $this->actor->id, ClasificacionArea::query()->firstOrFail()->id];
    }

    private function aprobar(Tramite $tramite, RespaldoTransitorio $respaldo): void
    {
        app(AprobarAntecedentesTransitoriosAction::class)->execute($tramite, $respaldo, $respaldo->versionActual->id, ['grado_eus' => 12, 'clasificacion_area_id' => ClasificacionArea::query()->firstOrFail()->id, 'cumple_normativa' => true], $this->actor);
    }

    private function competir(int $personaId, array $operaciones): array
    {
        $conexion = DB::connection();
        $conexion->beginTransaction();
        Persona::query()->lockForUpdate()->findOrFail($personaId);
        $procesos = [];
        try {
            foreach ($operaciones as $argumentos) {
                $comando = ['php', base_path('tests/Support/anular_transitorio_mysql_worker.php'), getenv('RRHH_MYSQL_3C2_TEST_DATABASE'), ...$argumentos];
                $proceso = proc_open($comando, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path());
                $this->assertIsResource($proceso);
                stream_set_timeout($pipes[1], 15);
                $this->assertSame('START', trim(fgets($pipes[1])));
                $procesos[] = [$proceso, $pipes];
            }
            usleep(300000);
            foreach ($procesos as [$proceso]) {
                $this->assertTrue(proc_get_status($proceso)['running']);
            }
            // Ambos procesos deben estar esperando en InnoDB, no solo vivos.
            $limite = microtime(true) + 10;
            do {
                $esperas = (int) DB::selectOne('select count(distinct REQUESTING_ENGINE_TRANSACTION_ID) as total from performance_schema.data_lock_waits')->total;
                if ($esperas >= 2) {
                    break;
                }
                usleep(50000);
            } while (microtime(true) < $limite);
            $this->assertGreaterThanOrEqual(2, $esperas);
        } finally {
            $conexion->commit();
        }
        $resultados = [];
        foreach ($procesos as [$proceso, $pipes]) {
            $resultados[] = trim(stream_get_contents($pipes[1]));
            $error = trim(stream_get_contents($pipes[2]));
            fclose($pipes[1]);
            fclose($pipes[2]);
            $this->assertSame(0, proc_close($proceso), $error);
        }
        sort($resultados);

        return $resultados;
    }

    private function caso(Persona $origen, Persona $candidato): array
    {
        $tipo = TipoTramite::query()->where('codigo', 'REEMPLAZO')->firstOrFail();
        $estado = EstadoTramite::query()->where('tipo_tramite_id', $tipo->id)->where('codigo', 'EN_REVISION')->firstOrFail();
        $tramite = Tramite::query()->create(['public_id' => (string) Str::ulid(), 'codigo' => 'MYSQL-C31-'.Str::upper(Str::random(10)), 'tipo_tramite_id' => $tipo->id, 'estado_tramite_id' => $estado->id, 'unidad_organizacional_id' => $this->unidad->id, 'created_by' => $this->actor->id]);
        $solicitud = SolicitudContrato::query()->forceCreate(['tramite_id' => $tramite->id, 'modalidad' => ModalidadSolicitudContrato::TRANSITORIA, 'unidad_solicitante_id' => $this->unidad->id, 'autoridad_persona_id' => $origen->id, 'autoridad_contexto' => ContextoAutoridadInstitucional::REGISTRO_SOLICITUD_CONTRATO, 'autoridad_resuelta_at' => now(), 'unidad_origen_id' => $this->unidad->id, 'unidad_destino_id' => $this->unidad->id]);
        TramiteReemplazo::query()->create(['tramite_id' => $tramite->id, 'funcionario_id' => $origen->id, 'tipo_reemplazo_id' => TipoReemplazo::query()->firstOrFail()->id, 'reemplazante_id' => $candidato->id, 'fecha_funcionario_desde' => '2026-10-01', 'fecha_funcionario_hasta' => '2026-10-20', 'fecha_reemplazante_desde' => '2026-10-05', 'fecha_reemplazante_hasta' => '2026-10-15', 'justificacion' => 'Prueba concurrente ficticia']);
        $respaldo = app(EscriturasPeriodosTransitorios::class)->registrarRespaldo($solicitud, $this->actor, ['funcionario_origen_id' => $origen->id, 'unidad_origen_id' => $this->unidad->id, 'motivo' => 'Prueba ficticia', 'fecha_desde' => '2026-10-01', 'fecha_hasta' => '2026-10-20']);

        $tramite->historial()->create(['user_id' => $this->actor->id, 'action_code' => 'ENVIAR_A_GESTION_PERSONAS', 'to_estado_id' => $estado->id, 'occurred_at' => now()]);

        return [$tramite, $respaldo];
    }

    private function persona(string $rut): Persona
    {
        return Persona::query()->create(['rut' => $rut, 'nombres' => 'Persona ficticia', 'active' => true]);
    }
}
