<?php

namespace Tests\Feature;

use App\Enums\AlcanceAccesoOperativo;
use App\Models\CalidadContractual;
use App\Models\Estamento;
use App\Models\Persona;
use App\Models\PersonaUnidadVinculo;
use App\Models\TipoReemplazo;
use App\Models\Tramite;
use App\Models\TramiteReemplazo;
use App\Models\UnidadOrganizacional;
use App\Models\User;
use App\Models\UserUnidadAcceso;
use Database\Seeders\ReemplazosV2Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ReemplazosV2BBorradorTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private UnidadOrganizacional $unidad;

    private Persona $funcionario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        foreach (['reemplazos.crear', 'tramites.adjuntos.cargar', 'tramites.adjuntos.descargar'] as $permiso) {
            Permission::findOrCreate($permiso);
        }
        $this->user = User::factory()->create(['active' => true]);
        $this->user->givePermissionTo(['reemplazos.crear', 'tramites.adjuntos.cargar', 'tramites.adjuntos.descargar']);
        $this->unidad = UnidadOrganizacional::query()->where('codigo', 'SDGADM-INF')->firstOrFail();
        UserUnidadAcceso::query()->create(['user_id' => $this->user->id, 'unidad_organizacional_id' => $this->unidad->id, 'alcance' => AlcanceAccesoOperativo::SOLO_UNIDAD, 'vigente_desde' => today(), 'created_by' => $this->user->id]);
        $this->funcionario = $this->persona('70000101-1', 'Funcionario Uno');
        $this->vincular($this->funcionario, $this->unidad);
    }

    public function test_authorized_user_opens_creation_and_outsider_cannot(): void
    {
        $this->actingAs($this->user)->get(route('reemplazos.create'))->assertOk()->assertSee('Nueva Solicitud de Reemplazo')->assertSee($this->unidad->nombre);
        $outsider = User::factory()->create(['active' => true]);
        $outsider->givePermissionTo('reemplazos.crear');
        $this->actingAs($outsider)->get(route('reemplazos.create'))->assertForbidden();
    }

    public function test_exact_scope_rejects_other_unit_and_descendant_scope_allows_child(): void
    {
        $rama = UnidadOrganizacional::query()->where('codigo', 'SDGADM')->firstOrFail();
        $datos = ['unidad_organizacional_id' => $rama->id];
        $this->actingAs($this->user)->post(route('reemplazos.store'), $datos)->assertForbidden();
        $this->user->accesosOperativos()->update(['vigente_hasta' => today()->subDay()]);
        UserUnidadAcceso::query()->create(['user_id' => $this->user->id, 'unidad_organizacional_id' => $rama->id, 'alcance' => AlcanceAccesoOperativo::UNIDAD_Y_DESCENDIENTES, 'vigente_desde' => today(), 'created_by' => $this->user->id]);
        $this->actingAs($this->user)->post(route('reemplazos.store'), ['unidad_organizacional_id' => $this->unidad->id])->assertRedirect();
    }

    public function test_creates_incomplete_draft_with_correct_type_state_unit_and_single_detail(): void
    {
        $response = $this->actingAs($this->user)->post(route('reemplazos.store'), ['unidad_organizacional_id' => $this->unidad->id]);
        $tramite = Tramite::query()->firstOrFail();
        $response->assertRedirect(route('reemplazos.edit', $tramite));
        $this->assertSame('REEMPLAZO', $tramite->tipoTramite->codigo);
        $this->assertSame('BORRADOR', $tramite->estadoTramite->codigo);
        $this->assertTrue($tramite->unidadOrganizacional->is($this->unidad));
        $this->assertSame(1, TramiteReemplazo::query()->where('tramite_id', $tramite->id)->count());
        $this->assertNull($tramite->reemplazo->reemplazante_id);
    }

    public function test_only_current_unit_staff_is_listed_and_manual_outside_staff_is_rejected(): void
    {
        $fuera = $this->persona('70000102-2', 'Persona Fuera');
        $this->actingAs($this->user)->getJson(route('reemplazos.funcionarios', ['unidad_organizacional_id' => $this->unidad->id]))->assertOk()->assertJsonFragment(['id' => $this->funcionario->id])->assertJsonMissing(['id' => $fuera->id]);
        $this->actingAs($this->user)->post(route('reemplazos.store'), ['unidad_organizacional_id' => $this->unidad->id, 'funcionario_id' => $fuera->id])->assertSessionHasErrors('funcionario_id');
    }

    public function test_existing_or_new_replacement_can_be_selected_without_creating_staff_link(): void
    {
        $reemplazante = $this->persona('70000103-3', 'Reemplazante');
        $this->actingAs($this->user)->post(route('reemplazos.store'), ['unidad_organizacional_id' => $this->unidad->id, 'reemplazante_id' => $reemplazante->id])->assertRedirect();
        $this->assertSame($reemplazante->id, TramiteReemplazo::query()->firstOrFail()->reemplazante_id);
        $this->assertSame(1, PersonaUnidadVinculo::count());

        $this->actingAs($this->user)->post(route('reemplazos.store'), ['unidad_organizacional_id' => $this->unidad->id, 'nuevo_reemplazante_rut' => '12.345.678-5', 'nuevo_reemplazante_nombres' => 'Nueva Persona'])->assertRedirect();
        $this->assertDatabaseHas('personas', ['rut' => '12345678-5']);
        $this->assertSame(1, PersonaUnidadVinculo::count());
    }

    public function test_complete_partial_invalid_and_same_person_rules_apply_to_draft(): void
    {
        $reemplazante = $this->persona('70000105-5', 'Cobertura');
        $base = ['unidad_organizacional_id' => $this->unidad->id, 'funcionario_id' => $this->funcionario->id, 'reemplazante_id' => $reemplazante->id, 'tipo_reemplazo_id' => TipoReemplazo::query()->firstOrFail()->id, 'fecha_funcionario_desde' => '2026-09-01', 'fecha_funcionario_hasta' => '2026-09-30', 'fecha_reemplazante_desde' => '2026-09-05', 'fecha_reemplazante_hasta' => '2026-09-25'];
        $this->actingAs($this->user)->post(route('reemplazos.store'), $base)->assertRedirect();
        $detalle = TramiteReemplazo::query()->firstOrFail();
        $this->assertSame(21, $detalle->diasReemplazante());
        $this->assertSame(9, $detalle->diasSinCobertura());
        $this->actingAs($this->user)->get(route('reemplazos.edit', $detalle->tramite))->assertSee('Quedarán 9 días sin cobertura');

        $this->actingAs($this->user)->put(route('reemplazos.update', $detalle->tramite), [...$base, 'reemplazante_id' => $this->funcionario->id])->assertSessionHasErrors('reemplazante_id');
        $this->actingAs($this->user)->put(route('reemplazos.update', $detalle->tramite), [...$base, 'fecha_reemplazante_desde' => '2026-08-31'])->assertSessionHasErrors('fecha_reemplazante_desde');
    }

    public function test_overlap_is_rejected_but_edit_does_not_collide_with_itself(): void
    {
        $datos = ['unidad_organizacional_id' => $this->unidad->id, 'funcionario_id' => $this->funcionario->id, 'tipo_reemplazo_id' => TipoReemplazo::query()->firstOrFail()->id, 'fecha_funcionario_desde' => '2026-09-01', 'fecha_funcionario_hasta' => '2026-09-15'];
        $this->actingAs($this->user)->post(route('reemplazos.store'), $datos)->assertRedirect();
        $tramite = Tramite::query()->firstOrFail();
        $this->actingAs($this->user)->put(route('reemplazos.update', $tramite), $datos)->assertRedirect();
        $this->actingAs($this->user)->post(route('reemplazos.store'), [...$datos, 'fecha_funcionario_desde' => '2026-09-10', 'fecha_funcionario_hasta' => '2026-09-20'])->assertSessionHasErrors('fecha_funcionario_desde');
    }

    public function test_out_of_scope_user_cannot_edit_or_change_to_an_unavailable_unit(): void
    {
        $this->actingAs($this->user)->post(route('reemplazos.store'), ['unidad_organizacional_id' => $this->unidad->id])->assertRedirect();
        $tramite = Tramite::query()->firstOrFail();
        $other = User::factory()->create(['active' => true]);
        $other->givePermissionTo('reemplazos.crear');
        $this->actingAs($other)->get(route('reemplazos.edit', $tramite))->assertForbidden();
        $rama = UnidadOrganizacional::query()->where('codigo', 'SDGA')->firstOrFail();
        $this->actingAs($this->user)->put(route('reemplazos.update', $tramite), ['unidad_organizacional_id' => $rama->id])->assertForbidden();
    }

    public function test_existing_private_attachments_are_available_after_first_save(): void
    {
        Storage::fake('private');
        $this->actingAs($this->user)->post(route('reemplazos.store'), ['unidad_organizacional_id' => $this->unidad->id]);
        $tramite = Tramite::query()->firstOrFail();
        $this->actingAs($this->user)->post(route('reemplazos.adjuntos.store', $tramite), ['archivo' => UploadedFile::fake()->create('respaldo.pdf', 20, 'application/pdf')])->assertRedirect();
        $this->assertDatabaseHas('tramite_adjuntos', ['tramite_id' => $tramite->id, 'original_name' => 'respaldo.pdf', 'version' => 1]);
    }

    public function test_replacement_catalog_seeder_is_idempotent(): void
    {
        $ids = TipoReemplazo::query()->pluck('id', 'codigo');
        $this->seed(ReemplazosV2Seeder::class);
        $this->assertSame(['CARGO_VACANTE', 'LICENCIA_MATERNAL', 'LICENCIA_MEDICA', 'PERMISO'], TipoReemplazo::query()->pluck('codigo')->sort()->values()->all());
        $this->assertSame($ids->all(), TipoReemplazo::query()->pluck('id', 'codigo')->all());
        $this->assertFalse(Schema::hasTable('reemplazo_coberturas'));
    }

    private function persona(string $rut, string $nombres): Persona
    {
        return Persona::query()->create(['rut' => $rut, 'nombres' => $nombres, 'active' => true]);
    }

    private function vincular(Persona $persona, UnidadOrganizacional $unidad): void
    {
        $estamento = Estamento::query()->firstOrFail();
        $calidad = CalidadContractual::query()->create(['codigo' => 'PLANTA_TEST', 'nombre' => 'Planta prueba', 'activo' => true]);
        PersonaUnidadVinculo::query()->create(['persona_id' => $persona->id, 'unidad_organizacional_id' => $unidad->id, 'estamento_id' => $estamento->id, 'calidad_contractual_id' => $calidad->id, 'cargo_funcion' => 'Cargo prueba', 'cargo_funcion_normalizado' => 'cargo prueba', 'vigente_desde' => today()->subYear(), 'origen' => 'MANUAL', 'created_by' => $this->user->id]);
    }
}
