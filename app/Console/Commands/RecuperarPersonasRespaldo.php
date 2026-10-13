<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Recuperacion\PlanRecuperacionPersonas;
use App\Support\Recuperacion\LegacySqlReader;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class RecuperarPersonasRespaldo extends Command
{
    protected $signature = 'recuperacion:personas
        {--archivo=database/recuperacion/nuevo.sql : Respaldo SQL solo para lectura}
        {--unidad-map= : JSON de correspondencias institucionales verificadas}
        {--mapa-manual-desarrollo : Permitir solo la simulación de candidatos cotejados manualmente en local/testing}
        {--report=database/recuperacion/reporte-recuperacion.json : Ruta de reporte JSON sin datos personales}
        {--dry-run : Simular explícitamente (también es el modo por defecto)}
        {--apply : Aplicar inserciones tras autorización externa}
        {--actor= : ID del usuario actual que deja constancia de la importación}';

    protected $description = 'Simula o aplica una recuperación controlada de personas y vínculos sin ejecutar SQL del respaldo';

    private array $mapMetadata = ['tipo' => 'SIN_MAPA'];

    public function handle(LegacySqlReader $reader, PlanRecuperacionPersonas $planner): int
    {
        $this->mapMetadata = ['tipo' => 'SIN_MAPA'];
        if ($this->option('apply') && $this->option('dry-run')) {
            $this->error('Use solo --apply o --dry-run.');

            return self::FAILURE;
        }
        if ($this->option('apply') && ! $this->validActor()) {
            $this->error('--apply requiere --actor de una cuenta activa existente.');

            return self::FAILURE;
        }

        try {
            $dump = $reader->read($this->absolutePath($this->option('archivo')));
            $unitMap = $this->readUnitMap($dump['sha256']);
            $this->validateReportPath($this->option('report'));
            if ($this->option('apply')) {
                $plan = DB::transaction(function () use ($dump, $unitMap, $planner): array {
                    $plan = $planner->build($dump, $unitMap);
                    $ids = [];
                    foreach ($plan['personas'] as $person) {
                        $ids[$person['source_id']] = $person['action'] === 'conservar'
                            ? $person['current_id']
                            : DB::table('personas')->insertGetId([...$person['data'], 'created_at' => now(), 'updated_at' => now()]);
                    }
                    foreach ($plan['vinculos'] as $link) {
                        if ($link['action'] !== 'crear') {
                            continue;
                        }
                        $data = collect($link)->only(['unidad_organizacional_id', 'estamento_id', 'profesion_id', 'calidad_contractual_id', 'cargo_funcion', 'cargo_funcion_normalizado', 'grado_eus', 'vigente_desde', 'vigente_hasta'])->all();
                        DB::table('persona_unidad_vinculos')->insert([
                            ...$data,
                            'persona_id' => $ids[$link['source_person_id']],
                            'origen' => 'IMPORTACION',
                            'origen_tramite_id' => null,
                            'observacion' => 'Recuperación controlada; respaldo SHA-256 '.$plan['sha256'].'; registro '.$link['source_id'],
                            'created_by' => (int) $this->option('actor'),
                            'updated_by' => null,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }

                    return $plan;
                }, 1);
            } else {
                $plan = $planner->build($dump, $unitMap);
            }
            $summary = $this->summary($plan);
            $this->table(['Tipo', 'Respaldo', 'Crear', 'Conservar', 'Conflicto'], [
                ['Personas', $summary['personas']['respaldo'], $summary['personas']['crear'], $summary['personas']['conservar'], $summary['personas']['conflicto']],
                ['Vínculos', $summary['vinculos']['respaldo'], $summary['vinculos']['crear'], $summary['vinculos']['conservar'], $summary['vinculos']['conflicto']],
            ]);
            $this->line('SHA-256 respaldo: '.$plan['sha256']);
            $this->line($this->option('apply') ? 'Aplicación completada.' : 'Simulación completada. Sin escrituras en la base de datos.');
            if ($this->mapMetadata['tipo'] === 'MANUAL_DESARROLLO') {
                $this->warn('MAPA MANUAL SOLO PARA SIMULACIÓN DE DESARROLLO. No prueba identidad histórica ni autoriza aplicar.');
            }
            foreach (collect($plan['conflictos'])->groupBy(fn ($conflict) => $conflict['tipo'].': '.$conflict['motivo']) as $reason => $items) {
                $this->line($reason.': '.count($items));
            }
            $this->writeReport($this->option('report'), $summary, $plan);

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error('Recuperación detenida: '.$exception->getMessage());

            return self::FAILURE;
        }
    }

    private function validActor(): bool
    {
        $id = $this->option('actor');

        if (! ctype_digit((string) $id)) {
            return false;
        }
        $actor = User::query()->find((int) $id);

        return $actor?->active && $actor->hasRole('Administrador') && $actor->can('dotacion.gestionar');
    }

    private function readUnitMap(string $sha256): array
    {
        $path = $this->option('unidad-map');
        if (! $path) {
            if ($this->option('mapa-manual-desarrollo')) {
                throw new RuntimeException('--mapa-manual-desarrollo requiere --unidad-map.');
            }

            return [];
        }
        $decoded = json_decode((string) file_get_contents($this->absolutePath($path)), true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($decoded) || ($decoded['dump_sha256'] ?? null) !== $sha256 || ! is_array($decoded['unidades'] ?? null)) {
            throw new RuntimeException('La correspondencia de unidades no coincide con el SHA-256 de este respaldo.');
        }

        $type = $decoded['tipo'] ?? 'VERIFICADO_INSTITUCIONAL';
        if ($type === 'MANUAL_DESARROLLO') {
            if ($this->option('apply')) {
                throw new RuntimeException('Un mapa manual de desarrollo nunca se puede usar con --apply.');
            }
            if (! $this->option('mapa-manual-desarrollo')
                || ! app()->environment(['local', 'testing'])
                || ! in_array(config('app.env'), ['local', 'testing'], true)) {
                throw new RuntimeException('El mapa manual requiere --mapa-manual-desarrollo y entorno local/testing.');
            }
            if (! is_string($decoded['procedencia'] ?? null) || trim($decoded['procedencia']) === ''
                || ! is_string($decoded['advertencia'] ?? null) || trim($decoded['advertencia']) === '') {
                throw new RuntimeException('El mapa manual debe registrar procedencia y advertencia.');
            }
            foreach ($decoded['unidades'] as $mapping) {
                if (! is_array($mapping) || ($mapping['estado'] ?? null) !== 'COTEJO_TECNICO_MANUAL_SOLO_DESARROLLO') {
                    throw new RuntimeException('Cada unidad del mapa manual debe indicar su estado de cotejo técnico.');
                }
            }
            $this->mapMetadata = ['tipo' => $type, 'procedencia' => $decoded['procedencia'], 'advertencia' => $decoded['advertencia']];
        } elseif ($type === 'VERIFICADO_INSTITUCIONAL' && ! $this->option('mapa-manual-desarrollo')) {
            $this->mapMetadata = ['tipo' => $type];
        } else {
            throw new RuntimeException('Tipo de mapa u opción --mapa-manual-desarrollo incompatible.');
        }

        return $decoded['unidades'];
    }

    private function summary(array $plan): array
    {
        $summary = [];
        foreach (['personas', 'vinculos'] as $type) {
            $conflictType = $type === 'personas' ? 'persona' : 'vinculo';
            $summary[$type] = [
                'respaldo' => $plan['source'][$type],
                'crear' => collect($plan[$type])->where('action', 'crear')->count(),
                'conservar' => collect($plan[$type])->where('action', 'conservar')->count(),
                'conflicto' => collect($plan['conflictos'])->where('tipo', $conflictType)->count(),
            ];
        }

        return $summary;
    }

    private function writeReport(string $path, array $summary, array $plan): void
    {
        $fullPath = $this->absolutePath($path);
        $report = ['sha256' => $plan['sha256'], 'mapa' => $this->mapMetadata, 'resumen' => $summary, 'conflictos' => $plan['conflictos']];
        if (file_put_contents($fullPath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), LOCK_EX) === false) {
            throw new RuntimeException('No se pudo escribir el reporte.');
        }
        if (function_exists('posix_geteuid') && posix_geteuid() === 0 && getenv('HOST_GID')) {
            chgrp($fullPath, (int) getenv('HOST_GID'));
        }
        chmod($fullPath, 0640);
        $this->line('Reporte: '.$fullPath);
    }

    private function validateReportPath(string $path): void
    {
        $fullPath = $this->absolutePath($path);
        if (! is_dir(dirname($fullPath)) || ! is_writable(dirname($fullPath)) || (file_exists($fullPath) && ! is_writable($fullPath))) {
            throw new RuntimeException('La ruta del reporte no permite escritura.');
        }
    }

    private function absolutePath(string $path): string
    {
        return str_starts_with($path, '/') ? $path : base_path($path);
    }
}
