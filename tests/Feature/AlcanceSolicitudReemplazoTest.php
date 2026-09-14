<?php

namespace Tests\Feature;

use App\Enums\AlcanceAccesoOperativo;
use App\Enums\TipoResponsabilidad;
use App\Models\CalidadContractual;
use App\Models\Estamento;
use App\Models\Persona;
use App\Models\PersonaUnidadVinculo;
use App\Models\Tramite;
use App\Models\UnidadOrganizacional;
use App\Models\UnidadResponsable;
use App\Models\User;
use App\Models\UserUnidadAcceso;
use App\Services\Reemplazos\AlcanceSolicitudReemplazoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AlcanceSolicitudReemplazoTest extends TestCase
{
    use RefreshDatabase;

    private AlcanceSolicitudReemplazoService $alcance;

    private User $user;

    private UnidadOrganizacional $unidad;

    private UnidadOrganizacional $otraUnidad;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Permission::findOrCreate('reemplazos.crear');
        $persona = $this->persona('71000001-1', 'Solicitante');
        $this->user = User::factory()->create(['active' => true, 'persona_id' => $persona->id]);
        $this->user->givePermissionTo('reemplazos.crear');
        [$this->unidad, $this->otraUnidad] = UnidadOrganizacional::query()->where('activo', true)->orderBy('id')->limit(2)->get()->all();
        $this->alcance = app(AlcanceSolicitudReemplazoService::class);
    }

    public function test_operational_access_alone_authorizes_the_unit(): void
    {
        $this->acceso($this->unidad);

        $this->assertTrue($this->alcance->puedeOperarEn($this->user, $this->unidad, today()));
        $this->actingAs($this->user)->get(route('reemplazos.create'))->assertOk()->assertSee($this->unidad->nombre);
    }

    public function test_current_approving_responsibility_alone_authorizes_holder_and_subrogate_without_descendants(): void
    {
        $this->responsabilidad($this->unidad, TipoResponsabilidad::TITULAR);
        $this->responsabilidad($this->otraUnidad, TipoResponsabilidad::SUBROGANTE);
        $descendiente = UnidadOrganizacional::query()->create([
            'tipo_unidad_organizacional_id' => $this->unidad->tipo_unidad_organizacional_id,
            'codigo' => 'ALCANCE-RESP-HIJA',
            'nombre' => 'Descendiente sin alcance',
            'parent_id' => $this->unidad->id,
            'activo' => true,
        ]);

        $ids = $this->alcance->unidadesAutorizadas($this->user, today())->pluck('id');

        $this->assertTrue($ids->contains($this->unidad->id));
        $this->assertTrue($ids->contains($this->otraUnidad->id));
        $this->assertFalse($ids->contains($descendiente->id));
        $this->actingAs($this->user)->get(route('reemplazos.create'))->assertOk()->assertSee($this->unidad->nombre)->assertSee($this->otraUnidad->nombre);
    }

    public function test_both_sources_are_deduplicated(): void
    {
        $this->acceso($this->unidad);
        $this->responsabilidad($this->unidad);

        $unidades = $this->alcance->unidadesAutorizadas($this->user, today());

        $this->assertSame(1, $unidades->where('id', $this->unidad->id)->count());
    }

    public function test_responsibility_without_approval_capacity_does_not_authorize(): void
    {
        $this->responsabilidad($this->unidad, TipoResponsabilidad::TITULAR, false);

        $this->assertFalse($this->alcance->puedeOperarEn($this->user, $this->unidad, today()));
        $this->actingAs($this->user)->get(route('reemplazos.create'))->assertForbidden();
    }

    public function test_expired_and_future_responsibilities_do_not_authorize(): void
    {
        $this->responsabilidad($this->unidad, TipoResponsabilidad::TITULAR, true, today()->subYear(), today()->subDay());
        $this->responsabilidad($this->otraUnidad, TipoResponsabilidad::TITULAR, true, today()->addDay());

        $this->assertTrue($this->alcance->unidadesAutorizadas($this->user, today())->isEmpty());
        $this->actingAs($this->user)->get(route('reemplazos.create'))->assertForbidden();
    }

    public function test_inactive_unit_is_ignored(): void
    {
        $this->responsabilidad($this->unidad);
        $this->unidad->update(['activo' => false]);

        $this->assertFalse($this->alcance->puedeOperarEn($this->user, $this->unidad->fresh(), today()));
        $this->actingAs($this->user)->get(route('reemplazos.create'))->assertForbidden();
    }

    public function test_user_without_person_has_no_responsibility_scope(): void
    {
        $user = User::factory()->create(['active' => true, 'persona_id' => null]);
        $user->givePermissionTo('reemplazos.crear');

        $this->assertTrue($this->alcance->unidadesAutorizadas($user, today())->isEmpty());
        $this->actingAs($user)->get(route('reemplazos.create'))->assertForbidden();
    }

    public function test_unit_outside_scope_is_denied_in_store_update_and_staff_endpoint(): void
    {
        $this->responsabilidad($this->unidad);
        $this->actingAs($this->user)->post(route('reemplazos.store'), ['unidad_organizacional_id' => $this->unidad->id])->assertRedirect();
        $tramite = Tramite::query()->firstOrFail();

        $this->assertTrue(Gate::forUser($this->user)->allows('view', $tramite));
        $this->actingAs($this->user)->post(route('reemplazos.store'), ['unidad_organizacional_id' => $this->otraUnidad->id])->assertForbidden();
        $this->actingAs($this->user)->put(route('reemplazos.update', $tramite), ['unidad_organizacional_id' => $this->otraUnidad->id])->assertForbidden();
        $this->actingAs($this->user)->getJson(route('reemplazos.funcionarios', ['unidad_organizacional_id' => $this->otraUnidad->id]))->assertForbidden();
    }

    public function test_replacement_staff_must_belong_to_selected_units_current_staff(): void
    {
        $this->responsabilidad($this->unidad);
        $dentro = $this->persona('71000002-2', 'Funcionario Dentro');
        $fuera = $this->persona('71000003-3', 'Funcionario Fuera');
        $this->vincular($dentro, $this->unidad);
        $this->vincular($fuera, $this->otraUnidad);

        $this->actingAs($this->user)
            ->getJson(route('reemplazos.funcionarios', ['unidad_organizacional_id' => $this->unidad->id]))
            ->assertOk()
            ->assertJsonFragment(['id' => $dentro->id])
            ->assertJsonMissing(['id' => $fuera->id]);
        $this->actingAs($this->user)->post(route('reemplazos.store'), ['unidad_organizacional_id' => $this->unidad->id, 'funcionario_id' => $fuera->id])->assertSessionHasErrors('funcionario_id');
        $this->actingAs($this->user)->post(route('reemplazos.store'), ['unidad_organizacional_id' => $this->unidad->id, 'funcionario_id' => $dentro->id])->assertRedirect();
    }

    public function test_replacement_form_loads_staff_from_existing_endpoint_when_unit_changes(): void
    {
        $this->responsabilidad($this->unidad);

        $this->actingAs($this->user)
            ->get(route('reemplazos.create'))
            ->assertOk()
            ->assertSee('reemplazos\\/funcionarios', false)
            ->assertSee('x-on:change="cargarFuncionarios()"', false)
            ->assertSee('Cargando funcionarios...')
            ->assertSee('No hay funcionarios vigentes en esta unidad.')
            ->assertDontSee('Guarde el borrador después de seleccionar la unidad');
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
        TipoResponsabilidad $tipo = TipoResponsabilidad::TITULAR,
        bool $puedeAprobar = true,
        string|\DateTimeInterface|null $desde = null,
        string|\DateTimeInterface|null $hasta = null,
    ): UnidadResponsable {
        return UnidadResponsable::query()->create([
            'unidad_organizacional_id' => $unidad->id,
            'persona_id' => $this->user->persona_id,
            'tipo' => $tipo,
            'vigente_desde' => $desde ?? today(),
            'vigente_hasta' => $hasta,
            'puede_aprobar' => $puedeAprobar,
            'created_by' => $this->user->id,
        ]);
    }

    private function vincular(Persona $persona, UnidadOrganizacional $unidad): PersonaUnidadVinculo
    {
        $calidad = CalidadContractual::query()->firstOrCreate(
            ['codigo' => 'ALCANCE_REEMPLAZO_TEST'],
            ['nombre' => 'Calidad alcance prueba', 'activo' => true],
        );

        return PersonaUnidadVinculo::query()->create([
            'persona_id' => $persona->id,
            'unidad_organizacional_id' => $unidad->id,
            'estamento_id' => Estamento::query()->firstOrFail()->id,
            'calidad_contractual_id' => $calidad->id,
            'cargo_funcion' => 'Cargo de prueba',
            'cargo_funcion_normalizado' => 'cargo de prueba',
            'vigente_desde' => today()->subYear(),
            'origen' => 'MANUAL',
            'created_by' => $this->user->id,
        ]);
    }

    private function persona(string $rut, string $nombres): Persona
    {
        return Persona::query()->create(['rut' => $rut, 'nombres' => $nombres, 'active' => true]);
    }
}
