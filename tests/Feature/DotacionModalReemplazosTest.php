<?php

namespace Tests\Feature;

use App\Enums\OrigenVinculoDotacion;
use App\Enums\TipoResponsabilidad;
use App\Models\CalidadContractual;
use App\Models\EstadoTramite;
use App\Models\Estamento;
use App\Models\Persona;
use App\Models\PersonaUnidadVinculo;
use App\Models\TipoReemplazo;
use App\Models\TipoTramite;
use App\Models\Tramite;
use App\Models\UnidadOrganizacional;
use App\Models\UnidadResponsable;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DotacionModalReemplazosTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private UnidadOrganizacional $unidad;

    private Estamento $estamento;

    private CalidadContractual $calidad;

    private Persona $funcionario;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        $this->admin = User::factory()->create(['active' => true]);
        $this->admin->assignRole('Administrador');
        $this->unidad = UnidadOrganizacional::query()->where('activo', true)->firstOrFail();
        $this->estamento = Estamento::query()->where('activo', true)->firstOrFail();
        $this->calidad = CalidadContractual::query()->where('activo', true)->firstOrFail();
        $this->funcionario = Persona::query()->create(['rut' => '99000001-1', 'nombres' => 'Jorge', 'apellido_paterno' => 'Cortés', 'apellido_materno' => 'Rojas', 'active' => true]);
        $this->crearVinculo($this->funcionario, '2026-01-01', null);
    }

    public function test_modal_muestra_grilla_resumen_y_relacion_bidireccional_del_reemplazo_vigente(): void
    {
        $reemplazante = Persona::query()->create(['rut' => '99000002-2', 'nombres' => 'Carlos', 'apellido_paterno' => 'Ferreira', 'active' => true]);
        $tramite = $this->crearReemplazo($reemplazante, '2026-09-01', '2026-09-15', 'TR-2026-000001');

        $this->actingAs($this->admin)
            ->get(route('admin.dotacion.index', ['fecha' => '2026-09-10']))
            ->assertOk()
            ->assertSee('2 funcionarios vigentes')
            ->assertSee('1 reemplazo')
            ->assertDontSee('titular')
            ->assertSee('grid grid-cols-1 gap-3 lg:grid-cols-2', false)
            ->assertSee('sm:max-w-6xl', false)
            ->assertSee('Buscar por nombre o RUT')
            ->assertSee('Reemplaza a Jorge Cortés Rojas')
            ->assertSee('Actualmente reemplazado por Carlos Ferreira')
            ->assertSee('01/09/2026 — 15/09/2026')
            ->assertSee($tramite->codigo)
            ->assertSee(route('admin.dotacion.persona', $this->funcionario), false)
            ->assertSee(route('admin.dotacion.persona', $reemplazante), false);
    }

    public function test_vinculo_normal_no_se_clasifica_como_reemplazo(): void
    {
        $normal = Persona::query()->create(['rut' => '99000003-3', 'nombres' => 'Persona', 'apellido_paterno' => 'Normal', 'active' => true]);
        $vinculo = $this->crearVinculo($normal, '2026-01-01', null);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.dotacion.index', ['fecha' => '2026-09-10']));

        $response->assertOk()->assertSee('Persona Normal')->assertSee('INTEGRANTE');
        $this->assertNull($response->viewData('reemplazosPorVinculo')->get($vinculo->id));
        $this->assertFalse($response->viewData('coberturasActuales')->has($this->unidad->id.':'.$normal->id));
    }

    public function test_titular_vigente_se_clasifica_como_jefatura_titular(): void
    {
        $this->crearResponsabilidad($this->funcionario, TipoResponsabilidad::TITULAR, '2026-01-01');

        $this->actingAs($this->admin)
            ->get(route('admin.dotacion.index', ['fecha' => '2026-09-10']))
            ->assertOk()
            ->assertSee('JEFATURA TITULAR');
    }

    public function test_subrogante_vigente_se_clasifica_como_subrogante(): void
    {
        $this->crearResponsabilidad($this->funcionario, TipoResponsabilidad::SUBROGANTE, '2026-01-01');

        $this->actingAs($this->admin)
            ->get(route('admin.dotacion.index', ['fecha' => '2026-09-10']))
            ->assertOk()
            ->assertSee('SUBROGANTE');
    }

    public function test_responsabilidad_historica_no_clasifica_el_vinculo_en_la_fecha_consultada(): void
    {
        $this->crearResponsabilidad($this->funcionario, TipoResponsabilidad::TITULAR, '2026-01-01', '2026-08-31');

        $this->actingAs($this->admin)
            ->get(route('admin.dotacion.index', ['fecha' => '2026-09-10']))
            ->assertOk()
            ->assertSee('INTEGRANTE')
            ->assertDontSee('JEFATURA TITULAR');
    }

    public function test_subrogancia_precede_a_titularidad_si_ambas_estan_vigentes(): void
    {
        $this->crearResponsabilidad($this->funcionario, TipoResponsabilidad::TITULAR, '2026-01-01');
        $this->crearResponsabilidad($this->funcionario, TipoResponsabilidad::SUBROGANTE, '2026-01-01');

        $this->actingAs($this->admin)
            ->get(route('admin.dotacion.index', ['fecha' => '2026-09-10']))
            ->assertOk()
            ->assertSee('SUBROGANTE')
            ->assertDontSee('JEFATURA TITULAR');
    }

    public function test_reemplazo_vigente_precede_a_responsabilidad_institucional(): void
    {
        $reemplazante = Persona::query()->create(['rut' => '99000007-7', 'nombres' => 'Reemplazante', 'apellido_paterno' => 'Responsable', 'active' => true]);
        $this->crearReemplazo($reemplazante, '2026-09-01', '2026-09-15', 'TR-2026-RESPONSABLE');
        $this->crearResponsabilidad($reemplazante, TipoResponsabilidad::SUBROGANTE, '2026-01-01');

        $response = $this->actingAs($this->admin)
            ->get(route('admin.dotacion.index', ['fecha' => '2026-09-10']));

        $vinculoReemplazante = PersonaUnidadVinculo::query()->where('persona_id', $reemplazante->id)->firstOrFail();
        $this->assertSame('REEMPLAZO', $response->viewData('rolesDotacion')->get($vinculoReemplazante->id)->value);
        $response->assertOk()->assertSee('REEMPLAZO')->assertDontSee('SUBROGANTE');
    }

    public function test_reemplazo_historico_no_aparece_como_cobertura_actual(): void
    {
        $historico = Persona::query()->create(['rut' => '99000004-4', 'nombres' => 'Reemplazo', 'apellido_paterno' => 'Histórico', 'active' => true]);
        $this->crearReemplazo($historico, '2026-08-01', '2026-08-15', 'TR-2026-HIST');

        $this->actingAs($this->admin)
            ->get(route('admin.dotacion.index', ['fecha' => '2026-09-10']))
            ->assertOk()
            ->assertDontSee('Actualmente reemplazado por Reemplazo Histórico');
    }

    public function test_subperiodos_identifican_al_reemplazante_correspondiente_a_la_fecha(): void
    {
        $primero = Persona::query()->create(['rut' => '99000005-5', 'nombres' => 'Carlos', 'apellido_paterno' => 'Primero', 'active' => true]);
        $segundo = Persona::query()->create(['rut' => '99000006-6', 'nombres' => 'Pedro', 'apellido_paterno' => 'Segundo', 'active' => true]);
        $this->crearReemplazo($primero, '2026-09-01', '2026-09-15', 'TR-2026-PRIMERO');
        $this->crearReemplazo($segundo, '2026-09-16', '2026-09-30', 'TR-2026-SEGUNDO');

        $this->actingAs($this->admin)
            ->get(route('admin.dotacion.index', ['fecha' => '2026-09-10']))
            ->assertOk()
            ->assertSee('Actualmente reemplazado por Carlos Primero')
            ->assertDontSee('Actualmente reemplazado por Pedro Segundo');

        $this->actingAs($this->admin)
            ->get(route('admin.dotacion.index', ['fecha' => '2026-09-20']))
            ->assertOk()
            ->assertSee('Actualmente reemplazado por Pedro Segundo')
            ->assertDontSee('Actualmente reemplazado por Carlos Primero');
    }

    private function crearReemplazo(Persona $reemplazante, string $desde, string $hasta, string $codigo): Tramite
    {
        $tipo = TipoTramite::query()->where('codigo', 'REEMPLAZO')->firstOrFail();
        $estado = EstadoTramite::query()->where('tipo_tramite_id', $tipo->id)->where('codigo', 'FORMALIZADA')->firstOrFail();
        $tramite = Tramite::query()->create([
            'public_id' => (string) Str::ulid(),
            'codigo' => $codigo,
            'tipo_tramite_id' => $tipo->id,
            'estado_tramite_id' => $estado->id,
            'unidad_organizacional_id' => $this->unidad->id,
            'created_by' => $this->admin->id,
        ]);
        $tramite->reemplazo()->create([
            'funcionario_id' => $this->funcionario->id,
            'reemplazante_id' => $reemplazante->id,
            'tipo_reemplazo_id' => TipoReemplazo::query()->firstOrFail()->id,
            'fecha_funcionario_desde' => $desde,
            'fecha_funcionario_hasta' => $hasta,
            'fecha_reemplazante_desde' => $desde,
            'fecha_reemplazante_hasta' => $hasta,
            'justificacion' => 'Continuidad operacional ficticia.',
        ]);
        $this->crearVinculo($reemplazante, $desde, $hasta, $tramite);

        return $tramite;
    }

    private function crearVinculo(Persona $persona, string $desde, ?string $hasta, ?Tramite $tramite = null): PersonaUnidadVinculo
    {
        return PersonaUnidadVinculo::query()->create([
            'persona_id' => $persona->id,
            'unidad_organizacional_id' => $this->unidad->id,
            'estamento_id' => $this->estamento->id,
            'calidad_contractual_id' => $this->calidad->id,
            'cargo_funcion' => 'Profesional de prueba',
            'cargo_funcion_normalizado' => 'profesional de prueba',
            'vigente_desde' => $desde,
            'vigente_hasta' => $hasta,
            'origen' => $tramite ? OrigenVinculoDotacion::DOCUMENTO_FIRMADO : OrigenVinculoDotacion::MANUAL,
            'origen_tramite_id' => $tramite?->id,
            'created_by' => $this->admin->id,
        ]);
    }

    private function crearResponsabilidad(Persona $persona, TipoResponsabilidad $tipo, string $desde, ?string $hasta = null): UnidadResponsable
    {
        return UnidadResponsable::query()->create([
            'unidad_organizacional_id' => $this->unidad->id,
            'persona_id' => $persona->id,
            'tipo' => $tipo,
            'vigente_desde' => $desde,
            'vigente_hasta' => $hasta,
            'puede_aprobar' => true,
            'created_by' => $this->admin->id,
        ]);
    }
}
