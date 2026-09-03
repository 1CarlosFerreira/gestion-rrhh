<?php

namespace Tests\Feature;

use App\Enums\AlcanceAccesoOperativo;
use App\Models\UnidadOrganizacional;
use App\Models\User;
use App\Models\UserUnidadAcceso;
use App\Services\Accesos\AccesoOperativoService;
use App\Services\EstructuraOrganizacionalService;
use Database\Seeders\EstructuraOrganizacionalOficialSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EstructuraOrganizacionalOficialTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_has_one_direction_root_and_three_direct_subdirections(): void
    {
        $direccion = $this->unidad('DIR');
        $this->assertNull($direccion->parent_id);
        $this->assertSame('DIRECCION', $direccion->tipo->codigo);
        $this->assertSame(1, UnidadOrganizacional::query()->whereNull('parent_id')->count());
        $this->assertSame(
            ['SDGA', 'SDGADM', 'SDGDP'],
            UnidadOrganizacional::query()->where('parent_id', $direccion->id)->whereHas('tipo', fn ($q) => $q->where('codigo', 'SUBDIRECCION'))->pluck('codigo')->sort()->values()->all(),
        );
    }

    public function test_official_dependencies_and_nested_units_are_correct(): void
    {
        $this->assertTrue($this->unidad('SDGADM-INF')->parent->is($this->unidad('SDGADM')));
        $this->assertSame(
            ['SDGADM-SSGG-ASEO', 'SDGADM-SSGG-LAV', 'SDGADM-SSGG-MOV'],
            $this->unidad('SDGADM-SSGG')->children()->pluck('codigo')->sort()->values()->all(),
        );
        $this->assertSame('UNIDAD', $this->unidad('SDGA-DEMANDA')->tipo->codigo);
        $this->assertSame(['SDGA-DEMANDA-GES', 'SDGA-DEMANDA-PREQ'], $this->unidad('SDGA-DEMANDA')->children()->pluck('codigo')->sort()->values()->all());
        $this->assertSame(['DIR-CGEST-CC', 'DIR-CGEST-EST', 'DIR-CGEST-GRD'], $this->unidad('DIR-CGEST')->children()->pluck('codigo')->sort()->values()->all());
    }

    public function test_seeder_is_idempotent_and_preserves_ids(): void
    {
        $ids = UnidadOrganizacional::query()->pluck('id', 'codigo');
        $count = $ids->count();
        $this->seed(EstructuraOrganizacionalOficialSeeder::class);
        $this->assertSame($count, UnidadOrganizacional::query()->count());
        $this->assertSame($ids->all(), UnidadOrganizacional::query()->pluck('id', 'codigo')->all());
    }

    public function test_official_tree_has_no_cycles_or_self_references(): void
    {
        $estructura = app(EstructuraOrganizacionalService::class);
        foreach (UnidadOrganizacional::query()->get() as $unidad) {
            $this->assertNotSame($unidad->id, $unidad->parent_id);
            $this->assertFalse($estructura->ancestros($unidad)->contains('id', $unidad->id));
        }
    }

    public function test_operational_access_resolves_official_administrative_tree(): void
    {
        $service = app(AccesoOperativoService::class);
        $user = User::factory()->create(['active' => true]);
        $this->acceso($user, 'SDGADM', AlcanceAccesoOperativo::UNIDAD_Y_DESCENDIENTES);
        $codes = $service->unidadesAccesibles($user, today())->pluck('codigo');
        foreach (['SDGADM', 'SDGADM-ABAST', 'SDGADM-ABAST-BC', 'SDGADM-ABAST-BFI', 'SDGADM-CONT', 'SDGADM-INF', 'SDGADM-SSGG', 'SDGADM-SSGG-MOV', 'SDGADM-SSGG-ASEO', 'SDGADM-SSGG-LAV', 'SDGADM-EII', 'SDGADM-EM'] as $codigo) {
            $this->assertTrue($codes->contains($codigo), "Falta {$codigo} en el alcance administrativo.");
        }
    }

    public function test_exact_and_services_generales_scopes_and_inactive_exclusion(): void
    {
        $service = app(AccesoOperativoService::class);
        $exacto = User::factory()->create(['active' => true]);
        $this->acceso($exacto, 'SDGADM-INF', AlcanceAccesoOperativo::SOLO_UNIDAD);
        $this->assertSame(['SDGADM-INF'], $service->unidadesAccesibles($exacto, today())->pluck('codigo')->all());

        $rama = User::factory()->create(['active' => true]);
        $this->acceso($rama, 'SDGADM-SSGG', AlcanceAccesoOperativo::UNIDAD_Y_DESCENDIENTES);
        $this->unidad('SDGADM-SSGG-ASEO')->update(['activo' => false]);
        $this->assertSame(
            ['SDGADM-SSGG', 'SDGADM-SSGG-LAV', 'SDGADM-SSGG-MOV'],
            $service->unidadesAccesibles($rama, today())->pluck('codigo')->sort()->values()->all(),
        );
    }

    public function test_people_management_scope_contains_both_areas_and_all_descendants(): void
    {
        $service = app(AccesoOperativoService::class);
        $user = User::factory()->create(['active' => true]);
        $this->acceso($user, 'SDGDP', AlcanceAccesoOperativo::UNIDAD_Y_DESCENDIENTES);
        $codes = $service->unidadesAccesibles($user, today())->pluck('codigo');
        foreach (['SDGDP-DGP', 'SDGDP-DGP-GP', 'SDGDP-DGP-CAP', 'SDGDP-DGP-RS', 'SDGDP-CV', 'SDGDP-CV-PRSO', 'SDGDP-CV-SC', 'SDGDP-CV-AL', 'SDGDP-CV-BIEN'] as $codigo) {
            $this->assertTrue($codes->contains($codigo));
        }
    }

    private function unidad(string $codigo): UnidadOrganizacional
    {
        return UnidadOrganizacional::query()->where('codigo', $codigo)->firstOrFail();
    }

    private function acceso(User $user, string $codigoUnidad, AlcanceAccesoOperativo $alcance): void
    {
        UserUnidadAcceso::query()->create(['user_id' => $user->id, 'unidad_organizacional_id' => $this->unidad($codigoUnidad)->id, 'alcance' => $alcance, 'vigente_desde' => today(), 'created_by' => $user->id]);
    }
}
