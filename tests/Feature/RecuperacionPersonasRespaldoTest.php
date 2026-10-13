<?php

namespace Tests\Feature;

use App\Models\CalidadContractual;
use App\Models\Estamento;
use App\Models\Persona;
use App\Models\TipoUnidadOrganizacional;
use App\Models\UnidadOrganizacional;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RecuperacionPersonasRespaldoTest extends TestCase
{
    use RefreshDatabase;

    private string $dumpPath;

    private string $mapPath;

    private string $reportPath;

    private UnidadOrganizacional $unit;

    private User $actor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dumpPath = tempnam(sys_get_temp_dir(), 'rrhh-dump-');
        $this->mapPath = tempnam(sys_get_temp_dir(), 'rrhh-map-');
        $this->reportPath = tempnam(sys_get_temp_dir(), 'rrhh-report-');
        $this->actor = User::factory()->create(['active' => true]);
        $permission = Permission::findOrCreate('dotacion.gestionar', 'web');
        $role = Role::findOrCreate('Administrador', 'web');
        $role->givePermissionTo($permission);
        $this->actor->assignRole($role);
        $type = TipoUnidadOrganizacional::query()->create(['codigo' => 'UNIDAD', 'nombre' => 'Unidad']);
        $this->unit = UnidadOrganizacional::query()->create(['codigo' => 'TEST', 'nombre' => 'Unidad Ficticia', 'tipo_unidad_organizacional_id' => $type->id]);
        Estamento::query()->create(['codigo' => 'ADMIN', 'nombre' => 'Administrativo']);
        CalidadContractual::query()->create(['codigo' => 'PLANTA', 'nombre' => 'Planta']);
        file_put_contents($this->dumpPath, $this->fixture());
        $this->writeMap();
    }

    protected function tearDown(): void
    {
        @unlink($this->dumpPath);
        @unlink($this->mapPath);
        @unlink($this->reportPath);
        parent::tearDown();
    }

    public function test_dry_run_never_writes_and_keeps_links_pending_without_unit_mapping(): void
    {
        $before = [DB::table('personas')->count(), DB::table('persona_unidad_vinculos')->count(), DB::table('users')->count()];
        $this->artisan('recuperacion:personas', ['--archivo' => $this->dumpPath, '--report' => $this->reportPath, '--dry-run' => true])
            ->expectsOutputToContain('unidad_sin_correspondencia_verificada: 1')
            ->assertSuccessful();
        $this->assertSame($before, [DB::table('personas')->count(), DB::table('persona_unidad_vinculos')->count(), DB::table('users')->count()]);
        $report = json_decode(file_get_contents($this->reportPath), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('unidad_sin_correspondencia_verificada', $report['conflictos'][0]['motivo']);
        $this->assertArrayNotHasKey('rut', $report['conflictos'][0]);
    }

    public function test_verified_mapping_allows_idempotent_import_on_isolated_test_database(): void
    {
        $options = ['--archivo' => $this->dumpPath, '--unidad-map' => $this->mapPath, '--report' => $this->reportPath];
        $this->artisan('recuperacion:personas', [...$options, '--dry-run' => true])
            ->expectsOutputToContain('Sin escrituras en la base de datos')
            ->assertSuccessful();
        $this->assertDatabaseMissing('personas', ['rut' => '12345678-5']);

        $this->assertSame(0, Artisan::call('recuperacion:personas', [...$options, '--apply' => true, '--actor' => $this->actor->id]), Artisan::output());
        $this->assertDatabaseHas('personas', ['rut' => '12345678-5']);
        $this->assertDatabaseHas('persona_unidad_vinculos', ['unidad_organizacional_id' => $this->unit->id, 'origen' => 'IMPORTACION', 'created_by' => $this->actor->id]);
        $counts = [DB::table('personas')->count(), DB::table('persona_unidad_vinculos')->count()];
        $this->assertSame(0, Artisan::call('recuperacion:personas', [...$options, '--apply' => true, '--actor' => $this->actor->id]), Artisan::output());
        $this->assertSame($counts, [DB::table('personas')->count(), DB::table('persona_unidad_vinculos')->count()]);
    }

    public function test_existing_person_difference_is_conflict_and_never_overwritten(): void
    {
        Persona::query()->create(['rut' => '12345678-5', 'nombres' => 'Nombre actual', 'active' => true]);
        $this->artisan('recuperacion:personas', ['--archivo' => $this->dumpPath, '--unidad-map' => $this->mapPath, '--report' => $this->reportPath, '--dry-run' => true])
            ->expectsOutputToContain('identidad_existente_difiere: 1')
            ->expectsOutputToContain('persona_no_resuelta: 1')
            ->assertSuccessful();
        $this->assertDatabaseHas('personas', ['rut' => '12345678-5', 'nombres' => 'Nombre actual']);
        $this->assertSame(0, DB::table('persona_unidad_vinculos')->count());
    }

    public function test_mismatched_unit_name_and_overlapping_link_are_reported(): void
    {
        $map = json_decode(file_get_contents($this->mapPath), true, 512, JSON_THROW_ON_ERROR);
        $map['unidades']['57']['nombre'] = 'Otra unidad';
        file_put_contents($this->mapPath, json_encode($map, JSON_THROW_ON_ERROR));
        $options = ['--archivo' => $this->dumpPath, '--unidad-map' => $this->mapPath, '--report' => $this->reportPath, '--dry-run' => true];
        $this->artisan('recuperacion:personas', $options)->expectsOutputToContain('unidad_destino_no_coincide: 1')->assertSuccessful();

        $this->writeMap();
        $person = Persona::query()->create(['rut' => '12345678-5', 'nombres' => 'Persona', 'apellido_paterno' => 'Ficticia', 'active' => true]);
        DB::table('persona_unidad_vinculos')->insert([
            'persona_id' => $person->id,
            'unidad_organizacional_id' => $this->unit->id,
            'estamento_id' => DB::table('estamentos')->value('id'),
            'calidad_contractual_id' => DB::table('calidades_contractuales')->value('id'),
            'cargo_funcion' => 'Cargo ficticio',
            'cargo_funcion_normalizado' => 'cargo ficticio',
            'vigente_desde' => '2026-01-10',
            'origen' => 'MANUAL',
            'created_by' => $this->actor->id,
        ]);
        $this->artisan('recuperacion:personas', $options)->expectsOutputToContain('vinculo_equivalente_solapado: 1')->assertSuccessful();
        $this->assertSame(1, DB::table('persona_unidad_vinculos')->count());
    }

    public function test_apply_requires_an_active_admin_with_dotacion_permission(): void
    {
        $unprivileged = User::factory()->create(['active' => true]);
        $this->artisan('recuperacion:personas', [
            '--archivo' => $this->dumpPath,
            '--unidad-map' => $this->mapPath,
            '--report' => $this->reportPath,
            '--apply' => true,
            '--actor' => $unprivileged->id,
        ])->assertFailed();
        $this->assertDatabaseMissing('personas', ['rut' => '12345678-5']);
    }

    public function test_manual_development_map_requires_explicit_dry_run_context_and_records_warning(): void
    {
        file_put_contents($this->mapPath, json_encode([
            'tipo' => 'MANUAL_DESARROLLO',
            'dump_sha256' => hash_file('sha256', $this->dumpPath),
            'procedencia' => 'Cotejo técnico ficticio, sin datos institucionales.',
            'advertencia' => 'Solo simulación; no prueba identidad histórica.',
            'unidades' => ['57' => [
                'codigo' => 'TEST',
                'nombre' => 'Unidad Ficticia',
                'estado' => 'COTEJO_TECNICO_MANUAL_SOLO_DESARROLLO',
                'evidencia' => 'Cargo ficticio y catálogo de prueba.',
            ]],
        ], JSON_THROW_ON_ERROR));
        $options = ['--archivo' => $this->dumpPath, '--unidad-map' => $this->mapPath, '--report' => $this->reportPath, '--dry-run' => true];
        $this->artisan('recuperacion:personas', $options)->assertFailed();
        $this->artisan('recuperacion:personas', [...$options, '--mapa-manual-desarrollo' => true])
            ->expectsOutputToContain('MAPA MANUAL SOLO PARA SIMULACIÓN DE DESARROLLO')
            ->assertSuccessful();
        $report = json_decode(file_get_contents($this->reportPath), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('MANUAL_DESARROLLO', $report['mapa']['tipo']);
        $this->assertSame(1, $report['resumen']['vinculos']['crear']);
        $this->assertSame(0, DB::table('persona_unidad_vinculos')->count());
        $this->assertDatabaseMissing('personas', ['rut' => '12345678-5']);

        config()->set('app.env', 'production');
        $this->artisan('recuperacion:personas', [...$options, '--mapa-manual-desarrollo' => true])->assertFailed();
    }

    private function writeMap(): void
    {
        file_put_contents($this->mapPath, json_encode([
            'dump_sha256' => hash_file('sha256', $this->dumpPath),
            'unidades' => ['57' => ['codigo' => 'TEST', 'nombre' => 'Unidad Ficticia', 'evidencia' => 'Acta ficticia de cotejo']],
        ], JSON_THROW_ON_ERROR));
    }

    private function fixture(): string
    {
        $tables = [
            'personas' => ['id', 'rut', 'nombres', 'apellido_paterno', 'apellido_materno', 'active', 'created_at', 'updated_at'],
            'persona_unidad_vinculos' => ['id', 'persona_id', 'unidad_organizacional_id', 'estamento_id', 'profesion_id', 'calidad_contractual_id', 'cargo_funcion', 'cargo_funcion_normalizado', 'grado_eus', 'vigente_desde', 'vigente_hasta', 'origen', 'origen_tramite_id', 'observacion', 'created_by', 'updated_by', 'created_at', 'updated_at'],
            'estamentos' => ['id', 'codigo', 'nombre', 'activo', 'created_at', 'updated_at'],
            'profesiones' => ['id', 'estamento_id', 'codigo', 'nombre', 'activo', 'created_at', 'updated_at'],
            'calidades_contractuales' => ['id', 'codigo', 'nombre', 'descripcion', 'activo', 'orden', 'created_at', 'updated_at'],
        ];
        $sql = "DROP TABLE IF EXISTS personas;\n";
        foreach ($tables as $name => $columns) {
            $sql .= "CREATE TABLE `{$name}` (\n";
            foreach ($columns as $column) {
                $sql .= "  `{$column}` varchar(200),\n";
            }
            $sql .= ") ENGINE=InnoDB;\n";
        }
        $sql .= "INSERT INTO `personas` VALUES (3,'12345678-5','Persona','Ficticia',NULL,1,NULL,NULL);\n";
        $sql .= "INSERT INTO `estamentos` VALUES (5,'ADMIN','Administrativo',1,NULL,NULL);\n";
        $sql .= "INSERT INTO `profesiones` VALUES (7,NULL,'OTRA','Otra',1,NULL,NULL);\n";
        $sql .= "INSERT INTO `calidades_contractuales` VALUES (6,'PLANTA','Planta',NULL,1,1,NULL,NULL);\n";
        $sql .= "INSERT INTO `persona_unidad_vinculos` VALUES (9,3,57,5,NULL,6,'Cargo ficticio','cargo ficticio',NULL,'2026-01-01',NULL,'MANUAL',NULL,NULL,1,NULL,NULL,NULL);\n";

        return $sql;
    }
}
