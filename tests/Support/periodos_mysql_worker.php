<?php

use App\Models\RespaldoAfectacion;
use App\Models\SolicitudContrato;
use App\Models\User;
use App\Services\Respaldos\EscriturasPeriodosTransitorios;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[$script, $database, $accion] = $argv;
if (! str_starts_with($database, 'gestion_rrhh_3b_test_')) {
    fwrite(STDERR, "Solo se admiten bases aisladas de prueba 3B.\n");
    exit(2);
}

config(['database.connections.mysql.database' => $database, 'database.default' => 'mysql']);
DB::purge('mysql');

fwrite(STDOUT, "START\n");
fflush(STDOUT);

try {
    $motor = $app->make(EscriturasPeriodosTransitorios::class);
    if ($accion === 'respaldo') {
        [$solicitudId, $actorId, $funcionarioId, $unidadId, $desde, $hasta] = array_slice($argv, 3);
        $registro = $motor->registrarRespaldo(
            SolicitudContrato::query()->findOrFail((int) $solicitudId),
            User::query()->findOrFail((int) $actorId),
            ['funcionario_origen_id' => (int) $funcionarioId, 'unidad_origen_id' => (int) $unidadId, 'motivo' => 'Prueba concurrente ficticia', 'fecha_desde' => $desde, 'fecha_hasta' => $hasta],
        );
    } elseif ($accion === 'reserva') {
        [$afectacionId, $actorId, $personaId, $desde, $hasta] = array_slice($argv, 3);
        $registro = $motor->registrarReserva(
            RespaldoAfectacion::query()->findOrFail((int) $afectacionId),
            User::query()->findOrFail((int) $actorId),
            (int) $personaId,
            $desde,
            $hasta,
        );
    } else {
        throw new RuntimeException('Acción de prueba desconocida.');
    }

    fwrite(STDOUT, 'OK:'.$registro->id."\n");
} catch (ValidationException) {
    fwrite(STDOUT, "CONFLICT\n");
} catch (Throwable $exception) {
    fwrite(STDERR, $exception::class.': '.$exception->getMessage()."\n");
    exit(2);
}
