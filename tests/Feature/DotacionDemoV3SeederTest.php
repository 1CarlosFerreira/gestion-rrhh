<?php

namespace Tests\Feature;

use App\Models\Persona;
use App\Models\PersonaUnidadVinculo;
use App\Models\Tramite;
use App\Models\UnidadOrganizacional;
use App\Models\UnidadResponsable;
use App\Models\User;
use App\Models\UserUnidadAcceso;
use App\Support\Rut\Rut;
use Database\Seeders\DotacionDemoV3Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use LogicException;
use Tests\TestCase;

class DotacionDemoV3SeederTest extends TestCase
{
    use RefreshDatabase;

    private string|false $previousOptIn;

    private string|false $previousPassword;

    protected function setUp(): void
    {
        parent::setUp();
        $this->previousOptIn = getenv('RRHH_ALLOW_DEMO_SEEDING');
        $this->previousPassword = getenv('RRHH_DEMO_PASSWORD');
        putenv('RRHH_ALLOW_DEMO_SEEDING=1');
        putenv('RRHH_DEMO_PASSWORD=ClaveFicticiaV3ParaPruebas');
        $this->seed();
    }

    protected function tearDown(): void
    {
        $this->restoreEnvironment('RRHH_ALLOW_DEMO_SEEDING', $this->previousOptIn);
        $this->restoreEnvironment('RRHH_DEMO_PASSWORD', $this->previousPassword);
        parent::tearDown();
    }

    public function test_creates_only_fictitious_people_links_users_and_accesses(): void
    {
        $existingUser = User::query()->firstOrFail();
        $existingPassword = $existingUser->password;

        $this->seed(DotacionDemoV3Seeder::class);

        $this->assertDatabaseCount('personas', 13);
        $this->assertDatabaseCount('persona_unidad_vinculos', 12);
        $this->assertDatabaseCount('user_unidad_accesos', 4);
        $this->assertDatabaseCount('unidad_responsables', 3);
        $this->assertDatabaseCount('tramites', 0);
        $this->assertSame($existingPassword, $existingUser->fresh()->password);

        $people = Persona::query()->where('apellido_materno', 'DemoV3')->orderBy('rut')->get();
        $this->assertCount(12, $people);
        foreach ($people as $person) {
            $this->assertTrue(Rut::validate($person->rut));
            $this->assertStringStartsWith('900000', $person->rut);
            $this->assertSame('DemoV3', $person->apellido_materno);
        }

        $unitCounts = PersonaUnidadVinculo::query()->join('unidades_organizacionales as u', 'u.id', '=', 'persona_unidad_vinculos.unidad_organizacional_id')
            ->selectRaw('u.codigo, count(*) as total')->groupBy('u.codigo')->pluck('total', 'u.codigo')->all();
        $this->assertEqualsCanonicalizing(['SDGADM-INF' => 5, 'SDGDP-DGP-GP' => 4, 'SDGADM' => 3], $unitCounts);
        $this->assertSame(10, PersonaUnidadVinculo::query()->vigentesEn(today())->count());
        $this->assertSame(2, PersonaUnidadVinculo::query()->whereDate('vigente_hasta', '2024-12-31')->count());

        $operator = User::query()->where('email', 'demo.v3.solicitante@example.test')->firstOrFail();
        $reviewer = User::query()->where('email', 'demo.v3.gestion@example.test')->firstOrFail();
        $this->assertTrue(Hash::check('ClaveFicticiaV3ParaPruebas', $operator->password));
        $this->assertTrue($operator->hasRole('Solicitante'));
        $this->assertTrue($reviewer->hasRole('Gestión de Personas'));
        $this->assertTrue($operator->can('reemplazos.crear'));
        $this->assertTrue($reviewer->can('reemplazos.revisar'));
        $this->assertSame(2, UserUnidadAcceso::query()->where('user_id', $operator->id)->count());
        $this->assertSame(2, UserUnidadAcceso::query()->where('user_id', $reviewer->id)->count());
        $this->assertEqualsCanonicalizing(
            ['SDGADM-INF', 'SDGDP-DGP-GP', 'SDGADM'],
            UnidadResponsable::query()->join('unidades_organizacionales as u', 'u.id', '=', 'unidad_responsables.unidad_organizacional_id')->pluck('u.codigo')->all(),
        );
    }

    public function test_second_run_preserves_records_and_passwords(): void
    {
        $this->seed(DotacionDemoV3Seeder::class);
        $operator = User::query()->where('email', 'demo.v3.solicitante@example.test')->firstOrFail();
        $password = $operator->password;
        $firstLinkIds = PersonaUnidadVinculo::query()->orderBy('id')->pluck('id')->all();

        $this->seed(DotacionDemoV3Seeder::class);

        $this->assertDatabaseCount('personas', 13);
        $this->assertDatabaseCount('persona_unidad_vinculos', 12);
        $this->assertDatabaseCount('user_unidad_accesos', 4);
        $this->assertDatabaseCount('unidad_responsables', 3);
        $this->assertSame($password, $operator->fresh()->password);
        $this->assertSame($firstLinkIds, PersonaUnidadVinculo::query()->orderBy('id')->pluck('id')->all());
        $this->assertDatabaseCount('tramites', 0);
    }

    public function test_collision_rolls_back_without_touching_existing_person(): void
    {
        $person = Persona::query()->create(['rut' => '90000001-'.$this->checkDigit('90000001'), 'nombres' => 'Identidad ajena', 'active' => true]);

        try {
            $this->seed(DotacionDemoV3Seeder::class);
            $this->fail('La colisión debe detener el seeder.');
        } catch (LogicException $exception) {
            $this->assertStringContainsString('Colisión de identidad ficticia', $exception->getMessage());
        }

        $this->assertSame('Identidad ajena', $person->fresh()->nombres);
        $this->assertDatabaseCount('personas', 2);
        $this->assertDatabaseCount('persona_unidad_vinculos', 0);
        $this->assertDatabaseCount('user_unidad_accesos', 0);
    }

    public function test_matching_identity_without_demo_provenance_is_rejected(): void
    {
        $person = Persona::query()->create([
            'rut' => '90000001-'.$this->checkDigit('90000001'),
            'nombres' => 'Ardelia',
            'apellido_paterno' => 'Nivar',
            'apellido_materno' => 'DemoV3',
            'active' => true,
        ]);

        try {
            $this->seed(DotacionDemoV3Seeder::class);
            $this->fail('Una identidad coincidente sin procedencia demo debe rechazarse.');
        } catch (LogicException $exception) {
            $this->assertStringContainsString('sin procedencia demo verificable', $exception->getMessage());
        }

        $this->assertSame('Ardelia', $person->fresh()->nombres);
        $this->assertDatabaseCount('personas', 2);
        $this->assertDatabaseCount('persona_unidad_vinculos', 0);
    }

    public function test_existing_account_collision_rolls_back_all_new_people(): void
    {
        $account = User::factory()->create(['email' => 'demo.v3.solicitante@example.test']);
        $password = $account->password;

        try {
            $this->seed(DotacionDemoV3Seeder::class);
            $this->fail('Una cuenta preexistente debe detener la carga completa.');
        } catch (LogicException $exception) {
            $this->assertStringContainsString('Colisión de cuenta demo', $exception->getMessage());
        }

        $this->assertSame($password, $account->fresh()->password);
        $this->assertDatabaseCount('personas', 1);
        $this->assertDatabaseCount('persona_unidad_vinculos', 0);
        $this->assertDatabaseCount('user_unidad_accesos', 0);
    }

    public function test_environment_guards_prevent_execution(): void
    {
        putenv('RRHH_ALLOW_DEMO_SEEDING');
        try {
            $this->seed(DotacionDemoV3Seeder::class);
            $this->fail('Se requiere opt-in explícito.');
        } catch (LogicException $exception) {
            $this->assertStringContainsString('solo admite local/testing', $exception->getMessage());
        }

        putenv('RRHH_ALLOW_DEMO_SEEDING=1');
        config()->set('app.env', 'production');
        try {
            $this->seed(DotacionDemoV3Seeder::class);
            $this->fail('Producción debe rechazarse.');
        } catch (LogicException $exception) {
            $this->assertStringContainsString('solo admite local/testing', $exception->getMessage());
        } finally {
            config()->set('app.env', 'testing');
        }

        $this->assertDatabaseCount('personas', 1);
    }

    public function test_password_is_required_before_any_write(): void
    {
        putenv('RRHH_DEMO_PASSWORD');

        try {
            $this->seed(DotacionDemoV3Seeder::class);
            $this->fail('Se requiere contraseña temporal para cuentas demo nuevas.');
        } catch (LogicException $exception) {
            $this->assertStringContainsString('RRHH_DEMO_PASSWORD', $exception->getMessage());
        }

        $this->assertDatabaseCount('personas', 1);
    }

    public function test_demo_operator_can_create_v3_transitory_request_in_isolated_test_database(): void
    {
        $this->seed(DotacionDemoV3Seeder::class);
        $unit = fn (string $code): int => UnidadOrganizacional::query()->where('codigo', $code)->firstOrFail()->id;
        $employee = Persona::query()->where('nombres', 'Belorio')->firstOrFail();
        $operator = User::query()->where('email', 'demo.v3.solicitante@example.test')->firstOrFail();

        $this->actingAs($operator)->post(route('reemplazos.store'), [
            'unidad_solicitante_id' => $unit('SDGADM'),
            'unidad_origen_id' => $unit('SDGADM-INF'),
            'unidad_destino_id' => $unit('SDGDP-DGP-GP'),
            'funcionario_id' => $employee->id,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseCount('tramites', 1);
        $this->assertSame($employee->id, Tramite::query()->firstOrFail()->reemplazo->funcionario_id);
    }

    private function restoreEnvironment(string $name, string|false $value): void
    {
        putenv($value === false ? $name : "{$name}={$value}");
    }

    private function checkDigit(string $body): string
    {
        $sum = 0;
        $factor = 2;
        for ($index = strlen($body) - 1; $index >= 0; $index--) {
            $sum += ((int) $body[$index]) * $factor;
            $factor = $factor === 7 ? 2 : $factor + 1;
        }

        return match (11 - ($sum % 11)) {
            11 => '0',
            10 => 'K',
            default => (string) (11 - ($sum % 11)),
        };
    }
}
