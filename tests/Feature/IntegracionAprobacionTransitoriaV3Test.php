<?php

namespace Tests\Feature;

use App\Enums\AlcanceAccesoOperativo;
use App\Enums\TipoResponsabilidad;
use App\Models\CalidadContractual;
use App\Models\ClasificacionArea;
use App\Models\Estamento;
use App\Models\Persona;
use App\Models\PersonaUnidadVinculo;
use App\Models\TipoReemplazo;
use App\Models\Tramite;
use App\Models\UnidadOrganizacional;
use App\Models\UnidadResponsable;
use App\Models\User;
use App\Models\UserUnidadAcceso;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IntegracionAprobacionTransitoriaV3Test extends TestCase
{
    use RefreshDatabase;

    private User $operador;

    private User $revisor;

    private UnidadOrganizacional $solicitante;

    private UnidadOrganizacional $origen;

    private UnidadOrganizacional $destino;

    private Persona $funcionario;

    private Persona $candidato;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->operador = User::factory()->create(['active' => true]);
        $this->operador->givePermissionTo('reemplazos.crear');
        $this->revisor = User::factory()->create(['active' => true]);
        $this->revisor->givePermissionTo('reemplazos.revisar');
        [$this->solicitante, $this->origen, $this->destino] = UnidadOrganizacional::query()->where('activo', true)->orderBy('id')->limit(3)->get()->all();
        foreach ([$this->solicitante, $this->origen, $this->destino] as $unidad) {
            foreach ([$this->operador, $this->revisor] as $actor) {
                UserUnidadAcceso::query()->create(['user_id' => $actor->id, 'unidad_organizacional_id' => $unidad->id, 'alcance' => AlcanceAccesoOperativo::SOLO_UNIDAD, 'vigente_desde' => today()->subDay(), 'created_by' => $this->operador->id]);
            }
        }
        $autoridad = $this->persona('85000001-1');
        UnidadResponsable::query()->create(['persona_id' => $autoridad->id, 'unidad_organizacional_id' => $this->solicitante->id, 'tipo' => TipoResponsabilidad::SUBROGANTE, 'vigente_desde' => today()->subDay(), 'puede_aprobar' => true, 'created_by' => $this->operador->id]);
        $this->funcionario = $this->persona('85000002-2');
        $this->candidato = $this->persona('85000003-3');
        $this->dotacion($this->funcionario);
    }

    public function test_public_flow_without_candidate_registers_backing_sends_reviews_and_approves(): void
    {
        [$tramite, $datos] = $this->crear(false);
        $this->assertNull($tramite->reemplazo->reemplazante_id);
        $this->actingAs($this->operador)->put(route('reemplazos.send', $tramite), $datos)->assertSessionHasErrors('respaldo');
        $this->assertSame('BORRADOR', $tramite->fresh()->estadoTramite->codigo);

        $this->registrarRespaldo($tramite);
        $this->actingAs($this->operador)->put(route('reemplazos.send', $tramite), $datos)->assertRedirect(route('dashboard'));
        $this->assertSame('ENVIADA_GESTION_PERSONAS', $tramite->fresh()->estadoTramite->codigo);
        $this->assertDatabaseCount('respaldo_afectaciones', 0);
        $this->assertDatabaseCount('reserva_persona_periodos', 0);
        $this->actingAs($this->revisor)->get(route('gestion-personas.reemplazos.index'))->assertOk()->assertSee('Pendiente de incorporar');
        $this->actingAs($this->revisor)->post(route('gestion-personas.reemplazos.start', $tramite))->assertRedirect();
        $this->assertSame('EN_REVISION', $tramite->fresh()->estadoTramite->codigo);
        $this->assertDatabaseCount('respaldo_afectaciones', 0);
        $this->actingAs($this->revisor)->get(route('gestion-personas.reemplazos.show', $tramite))->assertOk()->assertSee('Respaldo transitorio que se comprometerá');

        $this->actingAs($this->revisor)->put(route('gestion-personas.reemplazos.approve', $tramite), $this->revision($tramite))->assertRedirect();
        $this->assertSame('LISTA_GENERAR_DOCUMENTO', $tramite->fresh()->estadoTramite->codigo);
        $this->assertDatabaseCount('respaldo_afectaciones', 1);
        $this->assertDatabaseCount('reserva_persona_periodos', 0);
        $this->assertSame(1, $tramite->historial()->where('action_code', 'APROBAR_ANTECEDENTES')->count());
        $this->actingAs($this->revisor)->get(route('gestion-personas.reemplazos.show', $tramite))->assertOk()->assertSee('generación documental V3 aún no está habilitada')->assertDontSee('Generar documento');
    }

    public function test_public_flow_with_candidate_reserves_only_on_approval(): void
    {
        [$tramite, $datos] = $this->crear(true);
        $this->registrarRespaldo($tramite);
        $this->actingAs($this->operador)->put(route('reemplazos.send', $tramite), $datos)->assertRedirect(route('dashboard'));
        $this->actingAs($this->revisor)->post(route('gestion-personas.reemplazos.start', $tramite))->assertRedirect();
        $this->assertDatabaseCount('reserva_persona_periodos', 0);
        $this->actingAs($this->revisor)->put(route('gestion-personas.reemplazos.approve', $tramite), $this->revision($tramite))->assertRedirect();
        $this->assertDatabaseHas('reserva_persona_periodos', ['persona_id' => $this->candidato->id, 'liberado_at' => null]);
        $this->assertSame('LISTA_GENERAR_DOCUMENTO', $tramite->fresh()->estadoTramite->codigo);
        $this->actingAs($this->revisor)->put(route('gestion-personas.reemplazos.approve', $tramite), $this->revision($tramite))->assertSessionHasErrors();
        $this->assertDatabaseCount('respaldo_afectaciones', 1);
    }

    public function test_backing_registration_enforces_scope_identity_and_draft_state(): void
    {
        [$tramite, $datos] = $this->crear(false);
        $payload = [
            'motivo' => 'Licencia ficticia',
            'fecha_desde' => today()->addDay()->toDateString(),
            'fecha_hasta' => today()->addDays(10)->toDateString(),
            'funcionario_origen_id' => $this->candidato->id,
            'unidad_origen_id' => $this->destino->id,
        ];
        $fuera = User::factory()->create(['active' => true]);
        $fuera->givePermissionTo('reemplazos.crear');
        $this->actingAs($fuera)->post(route('reemplazos.respaldo-transitorio.store', $tramite), $payload)->assertForbidden();
        $this->operador->update(['active' => false]);
        $this->actingAs($this->operador)->post(route('reemplazos.respaldo-transitorio.store', $tramite), $payload)->assertForbidden();
        $this->operador->update(['active' => true]);

        $this->actingAs($this->operador)->post(route('reemplazos.respaldo-transitorio.store', $tramite), $payload)->assertRedirect(route('reemplazos.edit', $tramite));
        $version = $tramite->solicitudContrato->respaldosRegistrados()->firstOrFail()->versionActual;
        $this->assertSame($this->funcionario->id, $version->funcionario_origen_id);
        $this->assertSame($this->origen->id, $version->unidad_origen_id);

        $this->actingAs($this->operador)->put(route('reemplazos.send', $tramite), $datos)->assertRedirect(route('dashboard'));
        $this->actingAs($this->operador)->post(route('reemplazos.respaldo-transitorio.store', $tramite), $payload)->assertForbidden();
        $this->assertDatabaseCount('respaldos_transitorios', 1);
    }

    public function test_wrong_backing_id_permission_scope_and_document_route_are_blocked(): void
    {
        [$tramite, $datos] = $this->crear(false);
        $this->registrarRespaldo($tramite);
        $this->actingAs($this->operador)->put(route('reemplazos.send', $tramite), $datos)->assertRedirect();
        $this->actingAs($this->revisor)->post(route('gestion-personas.reemplazos.start', $tramite))->assertRedirect();

        $this->actingAs($this->revisor)->put(route('gestion-personas.reemplazos.approve', $tramite), [...$this->revision($tramite), 'respaldo_version_id' => 999999])->assertSessionHasErrors('respaldo_version_id');
        $otroFuncionario = $this->persona('85000005-5');
        $this->dotacion($otroFuncionario);
        [$ajeno] = $this->crear(false, $otroFuncionario);
        $this->registrarRespaldo($ajeno);
        $this->actingAs($this->revisor)->put(route('gestion-personas.reemplazos.approve', $tramite), [...$this->revision($tramite), 'respaldo_version_id' => $ajeno->solicitudContrato->respaldosRegistrados()->firstOrFail()->versionActual->id])->assertNotFound();
        $sinPermiso = User::factory()->create(['active' => true]);
        $this->actingAs($sinPermiso)->put(route('gestion-personas.reemplazos.approve', $tramite), $this->revision($tramite))->assertForbidden();
        $fuera = User::factory()->create(['active' => true]);
        $fuera->givePermissionTo('reemplazos.revisar');
        $this->actingAs($fuera)->put(route('gestion-personas.reemplazos.approve', $tramite), $this->revision($tramite))->assertForbidden();
        $this->assertDatabaseCount('respaldo_afectaciones', 0);

        $this->revisor->givePermissionTo('reemplazos.generar_documento');
        $this->actingAs($this->revisor)->put(route('gestion-personas.reemplazos.approve', $tramite), $this->revision($tramite))->assertRedirect();
        $this->actingAs($this->revisor)->post(route('reemplazos.documentos.store', $tramite))->assertForbidden();
        $this->assertDatabaseCount('documentos_generados', 0);
    }

    public function test_overlapping_candidate_rolls_back_second_public_approval(): void
    {
        [$primero, $datosPrimero] = $this->crear(true);
        $this->registrarRespaldo($primero);
        $this->actingAs($this->operador)->put(route('reemplazos.send', $primero), $datosPrimero)->assertRedirect();
        $this->actingAs($this->revisor)->post(route('gestion-personas.reemplazos.start', $primero))->assertRedirect();
        $this->actingAs($this->revisor)->put(route('gestion-personas.reemplazos.approve', $primero), $this->revision($primero))->assertRedirect();

        $otro = $this->persona('85000004-4');
        $this->dotacion($otro);
        [$segundo, $datosSegundo] = $this->crear(true, $otro);
        $this->registrarRespaldo($segundo);
        $this->actingAs($this->operador)->put(route('reemplazos.send', $segundo), $datosSegundo)->assertRedirect();
        $this->actingAs($this->revisor)->post(route('gestion-personas.reemplazos.start', $segundo))->assertRedirect();
        $this->actingAs($this->revisor)->put(route('gestion-personas.reemplazos.approve', $segundo), $this->revision($segundo))->assertSessionHasErrors('fecha_desde');
        $this->assertSame('EN_REVISION', $segundo->fresh()->estadoTramite->codigo);
        $this->assertSame(0, $segundo->historial()->where('action_code', 'APROBAR_ANTECEDENTES')->count());
        $this->assertDatabaseCount('respaldo_afectaciones', 1);
        $this->assertDatabaseCount('reserva_persona_periodos', 1);
    }

    private function crear(bool $conCandidato, ?Persona $funcionario = null): array
    {
        $funcionario ??= $this->funcionario;
        $datos = [
            'unidad_solicitante_id' => $this->solicitante->id,
            'unidad_origen_id' => $this->origen->id,
            'unidad_destino_id' => $this->destino->id,
            'funcionario_id' => $funcionario->id,
            'tipo_reemplazo_id' => TipoReemplazo::query()->firstOrFail()->id,
            'fecha_funcionario_desde' => today()->addDay()->toDateString(),
            'fecha_funcionario_hasta' => today()->addDays(10)->toDateString(),
            'justificacion' => 'Continuidad ficticia del servicio.',
        ];
        if ($conCandidato) {
            $datos += [
                'reemplazante_id' => $this->candidato->id,
                'reemplazante_estamento_id' => Estamento::query()->firstOrFail()->id,
                'reemplazante_calidad_contractual_id' => CalidadContractual::query()->firstOrFail()->id,
                'reemplazante_cargo_funcion' => 'Cargo ficticio',
                'fecha_reemplazante_desde' => today()->addDays(2)->toDateString(),
                'fecha_reemplazante_hasta' => today()->addDays(9)->toDateString(),
            ];
        }
        $this->actingAs($this->operador)->post(route('reemplazos.store'), $datos)->assertRedirect();

        return [Tramite::query()->latest('id')->firstOrFail(), $datos];
    }

    private function registrarRespaldo(Tramite $tramite): void
    {
        $this->actingAs($this->operador)->post(route('reemplazos.respaldo-transitorio.store', $tramite), [
            'motivo' => 'Licencia ficticia',
            'fecha_desde' => today()->addDay()->toDateString(),
            'fecha_hasta' => today()->addDays(10)->toDateString(),
        ])->assertRedirect(route('reemplazos.edit', $tramite));
    }

    private function revision(Tramite $tramite): array
    {
        return [
            'grado_eus' => 12,
            'clasificacion_area_id' => ClasificacionArea::query()->firstOrFail()->id,
            'cumple_normativa' => true,
            'respaldo_version_id' => $tramite->solicitudContrato->respaldosRegistrados()->firstOrFail()->versionActual->id,
        ];
    }

    private function dotacion(Persona $persona): void
    {
        PersonaUnidadVinculo::query()->create(['persona_id' => $persona->id, 'unidad_organizacional_id' => $this->origen->id, 'estamento_id' => Estamento::query()->firstOrFail()->id, 'calidad_contractual_id' => CalidadContractual::query()->firstOrFail()->id, 'cargo_funcion' => 'Cargo ficticio', 'cargo_funcion_normalizado' => 'cargo ficticio', 'vigente_desde' => today()->subYear(), 'origen' => 'MANUAL', 'created_by' => $this->operador->id]);
    }

    private function persona(string $rut): Persona
    {
        return Persona::query()->create(['rut' => $rut, 'nombres' => 'Persona ficticia', 'active' => true]);
    }
}
