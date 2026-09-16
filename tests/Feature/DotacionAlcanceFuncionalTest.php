<?php

namespace Tests\Feature;

use App\Enums\AlcanceAccesoOperativo;
use App\Enums\TipoResponsabilidad;
use App\Models\CalidadContractual;
use App\Models\Estamento;
use App\Models\Persona;
use App\Models\PersonaUnidadVinculo;
use App\Models\UnidadOrganizacional;
use App\Models\UnidadResponsable;
use App\Models\User;
use App\Models\UserUnidadAcceso;
use App\Services\Alcances\AlcanceFuncionalUnidadResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class DotacionAlcanceFuncionalTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private UnidadOrganizacional $unidadAcceso;

    private UnidadOrganizacional $unidadResponsabilidad;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        foreach (['dotacion.ver', 'dotacion.ver_todas', 'dotacion.gestionar'] as $permiso) {
            Permission::findOrCreate($permiso);
        }

        $persona = Persona::query()->create(['rut' => '72000001-1', 'nombres' => 'Lector', 'active' => true]);
        $this->user = User::factory()->create(['active' => true, 'persona_id' => $persona->id]);
        $this->user->givePermissionTo('dotacion.ver');
        [$this->unidadAcceso, $this->unidadResponsabilidad] = UnidadOrganizacional::query()
            ->where('activo', true)
            ->orderBy('id')
            ->limit(2)
            ->get()
            ->all();
    }

    public function test_solo_acceso_operativo_habilita_lectura(): void
    {
        $this->acceso($this->unidadAcceso);
        $vinculo = $this->vinculo($this->unidadAcceso, 'Visible por acceso');

        $this->assertTrue($this->user->can('view', $vinculo));
        $this->actingAs($this->user)->get(route('admin.dotacion.index'))->assertOk()->assertSee('Visible por acceso');
    }

    public function test_solo_responsabilidad_vigente_con_aprobacion_habilita_lectura(): void
    {
        $this->responsabilidad($this->unidadResponsabilidad);
        $vinculo = $this->vinculo($this->unidadResponsabilidad, 'Visible por responsabilidad');

        $this->assertTrue($this->user->can('viewAny', PersonaUnidadVinculo::class));
        $this->assertTrue($this->user->can('view', $vinculo));
        $this->actingAs($this->user)->get(route('admin.dotacion.index'))->assertOk()->assertSee('Visible por responsabilidad');
        $this->actingAs($this->user)->get(route('admin.dotacion.persona', $vinculo->persona))->assertOk()->assertSee('Visible por responsabilidad');
    }

    public function test_combina_ambas_fuentes_sin_duplicar_unidades(): void
    {
        $this->acceso($this->unidadAcceso);
        $this->responsabilidad($this->unidadAcceso);
        $this->responsabilidad($this->unidadResponsabilidad);

        $unidades = app(AlcanceFuncionalUnidadResolver::class)->unidadesAutorizadas($this->user, today());

        $this->assertEqualsCanonicalizing(
            [$this->unidadAcceso->id, $this->unidadResponsabilidad->id],
            $unidades->pluck('id')->all(),
        );
        $this->assertSame(1, $unidades->where('id', $this->unidadAcceso->id)->count());
    }

    public function test_responsabilidad_sin_aprobacion_no_amplia_lectura(): void
    {
        $this->responsabilidad($this->unidadResponsabilidad, puedeAprobar: false);

        $this->assertFalse($this->user->can('viewAny', PersonaUnidadVinculo::class));
        $this->actingAs($this->user)->get(route('admin.dotacion.index'))->assertForbidden();
    }

    public function test_responsabilidad_vencida_no_amplia_lectura(): void
    {
        $this->responsabilidad($this->unidadResponsabilidad, hasta: today()->subDay());

        $this->assertFalse($this->user->can('viewAny', PersonaUnidadVinculo::class));
    }

    public function test_unidad_inactiva_no_amplia_lectura(): void
    {
        $this->responsabilidad($this->unidadResponsabilidad);
        $this->unidadResponsabilidad->update(['activo' => false]);

        $this->assertFalse($this->user->can('viewAny', PersonaUnidadVinculo::class));
    }

    public function test_permiso_ver_todas_conserva_lectura_institucional_completa(): void
    {
        $this->user->givePermissionTo('dotacion.ver_todas');
        $visible = $this->vinculo($this->unidadAcceso, 'Visible institucional uno');
        $otro = $this->vinculo($this->unidadResponsabilidad, 'Visible institucional dos');

        $this->assertTrue($this->user->can('view', $visible));
        $this->assertTrue($this->user->can('view', $otro));
        $this->actingAs($this->user)
            ->get(route('admin.dotacion.index'))
            ->assertOk()
            ->assertSee('Visible institucional uno')
            ->assertSee('Visible institucional dos');
    }

    public function test_responsabilidad_no_amplia_escritura(): void
    {
        $this->user->givePermissionTo('dotacion.gestionar');
        $this->responsabilidad($this->unidadResponsabilidad);
        $vinculo = $this->vinculo($this->unidadResponsabilidad, 'No editable por responsabilidad');

        $this->assertFalse($this->user->can('create', [PersonaUnidadVinculo::class, $this->unidadResponsabilidad]));
        $this->assertFalse($this->user->can('update', $vinculo));
        $this->actingAs($this->user)->get(route('admin.dotacion.create'))->assertForbidden();
        $this->actingAs($this->user)->get(route('admin.dotacion.edit', $vinculo))->assertForbidden();
        $this->actingAs($this->user)
            ->patch(route('admin.dotacion.close', $vinculo), ['vigente_hasta' => today()->toDateString()])
            ->assertForbidden();
    }

    private function acceso(UnidadOrganizacional $unidad): UserUnidadAcceso
    {
        return UserUnidadAcceso::query()->create([
            'user_id' => $this->user->id,
            'unidad_organizacional_id' => $unidad->id,
            'alcance' => AlcanceAccesoOperativo::SOLO_UNIDAD,
            'vigente_desde' => today(),
            'created_by' => $this->user->id,
        ]);
    }

    private function responsabilidad(
        UnidadOrganizacional $unidad,
        bool $puedeAprobar = true,
        string|\DateTimeInterface|null $hasta = null,
    ): UnidadResponsable {
        return UnidadResponsable::query()->create([
            'unidad_organizacional_id' => $unidad->id,
            'persona_id' => $this->user->persona_id,
            'tipo' => TipoResponsabilidad::TITULAR,
            'vigente_desde' => today()->subMonth(),
            'vigente_hasta' => $hasta,
            'puede_aprobar' => $puedeAprobar,
            'created_by' => $this->user->id,
        ]);
    }

    private function vinculo(UnidadOrganizacional $unidad, string $cargo): PersonaUnidadVinculo
    {
        return PersonaUnidadVinculo::query()->create([
            'persona_id' => Persona::query()->create([
                'rut' => fake()->unique()->numerify('73######-#'),
                'nombres' => $cargo,
                'active' => true,
            ])->id,
            'unidad_organizacional_id' => $unidad->id,
            'estamento_id' => Estamento::query()->firstOrFail()->id,
            'calidad_contractual_id' => CalidadContractual::query()->firstOrFail()->id,
            'cargo_funcion' => $cargo,
            'cargo_funcion_normalizado' => mb_strtolower($cargo),
            'vigente_desde' => today()->subMonth(),
            'origen' => 'MANUAL',
            'created_by' => $this->user->id,
        ]);
    }
}
