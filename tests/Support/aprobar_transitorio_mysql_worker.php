<?php

use App\Actions\Reemplazos\AprobarAntecedentesTransitoriosAction;
use App\Models\RespaldoTransitorio;
use App\Models\Tramite;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[$script, $database, $tramiteId, $respaldoId, $versionId, $actorId, $clasificacionId] = $argv;
if (! str_starts_with($database, 'gestion_rrhh_3c1_test_')) {
    fwrite(STDERR, "Solo se admiten bases aisladas de prueba 3C.1.\n");
    exit(2);
}

config(['database.connections.mysql.database' => $database, 'database.default' => 'mysql']);
DB::purge('mysql');
fwrite(STDOUT, "START\n");
fflush(STDOUT);

try {
    $app->make(AprobarAntecedentesTransitoriosAction::class)->execute(
        Tramite::query()->findOrFail((int) $tramiteId),
        RespaldoTransitorio::query()->findOrFail((int) $respaldoId),
        (int) $versionId,
        ['grado_eus' => 12, 'clasificacion_area_id' => (int) $clasificacionId, 'cumple_normativa' => true],
        User::query()->findOrFail((int) $actorId),
    );
    fwrite(STDOUT, "OK\n");
} catch (ValidationException) {
    fwrite(STDOUT, "CONFLICT\n");
} catch (Throwable $exception) {
    fwrite(STDERR, $exception::class.': '.$exception->getMessage()."\n");
    exit(2);
}
