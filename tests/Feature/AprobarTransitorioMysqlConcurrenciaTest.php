<?php

namespace Tests\Feature;

use App\Enums\ContextoAutoridadInstitucional;
use App\Enums\ModalidadSolicitudContrato;
use App\Models\ClasificacionArea;
use App\Models\EstadoTramite;
use App\Models\Persona;
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

class AprobarTransitorioMysqlConcurrenciaTest extends TestCase
{
    private User $actor;

    private UnidadOrganizacional $unidad;

    private Persona $candidato;

    protected function setUp(): void
    {
        parent::setUp();
        $database = getenv('RRHH_MYSQL_3C1_TEST_DATABASE');
        if (! is_string($database) || ! str_starts_with($database, 'gestion_rrhh_3c1_test_')) {
            $this->markTestSkipped('Requiere una base MySQL 8.4 aislada y vacía con prefijo gestion_rrhh_3c1_test_.');
        }

        $config = config('database.connections.mysql');
        $config['database'] = $database;
        config(['database.connections.mysql_3c1' => $config, 'database.default' => 'mysql_3c1']);
        DB::purge('mysql_3c1');
        $this->assertSame('8.4', substr(DB::connection()->selectOne('select version() as version')->version, 0, 3));
        $this->assertSame([], DB::select('show tables'));
        Artisan::call('migrate', ['--database' => 'mysql_3c1', '--force' => true]);
        $this->seed();

        $this->actor = User::factory()->create(['active' => true]);
        $this->actor->givePermissionTo(['reemplazos.revisar', 'reemplazos.alcance_global']);
        $this->unidad = UnidadOrganizacional::query()->firstOrFail();
        $this->candidato = $this->persona('86000001-1');
    }

    public function test_independent_approvals_serialize_by_request_and_candidate(): void
    {
        [$primero, $respaldo] = $this->caso($this->persona('86000002-2'), $this->candidato);
        $this->assertSame(['CONFLICT', 'OK'], $this->competir($this->candidato->id, [[$primero, $respaldo], [$primero, $respaldo]]));
        $this->assertDatabaseCount('respaldo_afectaciones', 1);
        $this->assertDatabaseCount('reserva_persona_periodos', 1);
        $this->assertSame(1, $primero->historial()->where('action_code', 'APROBAR_ANTECEDENTES')->count());

        $otroCandidato = $this->persona('86000005-5');
        [$segundo, $otroRespaldo] = $this->caso($this->persona('86000003-3'), $otroCandidato);
        [$tercero, $tercerRespaldo] = $this->caso($this->persona('86000004-4'), $otroCandidato);
        $this->assertSame(['CONFLICT', 'OK'], $this->competir($otroCandidato->id, [[$segundo, $otroRespaldo], [$tercero, $tercerRespaldo]]));
        $this->assertDatabaseCount('respaldo_afectaciones', 2);
        $this->assertDatabaseCount('reserva_persona_periodos', 2);
        $this->assertSame(1, $segundo->historial()->where('action_code', 'APROBAR_ANTECEDENTES')->count()
            + $tercero->historial()->where('action_code', 'APROBAR_ANTECEDENTES')->count());
        $this->assertSame(1, $segundo->revisionReemplazo()->count() + $tercero->revisionReemplazo()->count());
    }

    private function competir(int $personaId, array $casos): array
    {
        $conexion = DB::connection();
        $conexion->beginTransaction();
        Persona::query()->lockForUpdate()->findOrFail($personaId);
        $procesos = [];

        try {
            foreach ($casos as [$tramite, $respaldo]) {
                $comando = ['php', base_path('tests/Support/aprobar_transitorio_mysql_worker.php'), getenv('RRHH_MYSQL_3C1_TEST_DATABASE'), $tramite->id, $respaldo->id, $respaldo->versionActual->id, $this->actor->id, ClasificacionArea::query()->firstOrFail()->id];
                $proceso = proc_open($comando, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path());
                $this->assertIsResource($proceso);
                $this->assertSame('START', trim(fgets($pipes[1])));
                $procesos[] = [$proceso, $pipes];
            }
            usleep(300000);
            foreach ($procesos as [$proceso]) {
                $this->assertTrue(proc_get_status($proceso)['running']);
            }
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

        return [$tramite, $respaldo];
    }

    private function persona(string $rut): Persona
    {
        return Persona::query()->create(['rut' => $rut, 'nombres' => 'Persona ficticia', 'active' => true]);
    }
}
