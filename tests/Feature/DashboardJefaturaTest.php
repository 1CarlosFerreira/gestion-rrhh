<?php

namespace Tests\Feature;

use App\Enums\TipoResponsabilidad;
use App\Models\CalidadContractual;
use App\Models\Estamento;
use App\Models\Persona;
use App\Models\PersonaUnidadVinculo;
use App\Models\UnidadOrganizacional;
use App\Models\UnidadResponsable;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardJefaturaTest extends TestCase
{
    use RefreshDatabase;

    private User $jefatura;

    private UnidadOrganizacional $unidadTitular;

    private UnidadOrganizacional $unidadSubrogante;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $persona = Persona::query()->create(['rut' => '76000001-1', 'nombres' => 'Jefatura', 'apellido_paterno' => 'Prueba', 'active' => true]);
        $this->jefatura = User::factory()->create(['persona_id' => $persona->id, 'active' => true, 'name' => 'Jefatura Prueba']);
        $this->jefatura->assignRole('Jefatura');
        $this->unidadTitular = UnidadOrganizacional::query()->where('codigo', 'SDGADM-EM')->firstOrFail();
        $this->unidadSubrogante = UnidadOrganizacional::query()->where('codigo', 'SDGADM-INF')->firstOrFail();

        $this->responsabilidad($this->unidadTitular, TipoResponsabilidad::TITULAR);
        $this->responsabilidad($this->unidadSubrogante, TipoResponsabilidad::SUBROGANTE);
    }

    public function test_jefatura_pura_recibe_dashboard_y_navegacion_coherentes_con_sus_permisos(): void
    {
        $response = $this->actingAs($this->jefatura)->get(route('dashboard'));

        $response->assertOk()
            ->assertSee('Consulta la dotación y estructura organizacional de las unidades dentro de tu ámbito de operación.')
            ->assertDontSee('Gestiona y realiza seguimiento a tus solicitudes.')
            ->assertSee('Inicio')
            ->assertSee('Dotación')
            ->assertSee('Organización')
            ->assertSee('Estructura organizacional')
            ->assertSee('Organigrama')
            ->assertDontSee('Administración<span', false)
            ->assertDontSee('>Trámites<', false)
            ->assertDontSee('Usuarios')
            ->assertDontSee('Roles y permisos')
            ->assertDontSee('Responsables')
            ->assertDontSee('Accesos operativos')
            ->assertDontSee('Calidades contractuales')
            ->assertSee(route('admin.dotacion.index'), false)
            ->assertSee(route('admin.estructura.index'), false)
            ->assertSee(route('admin.estructura.organigrama'), false);

        $this->actingAs($this->jefatura)->get(route('admin.estructura.index'))->assertOk();
        $this->actingAs($this->jefatura)->get(route('admin.estructura.organigrama'))->assertOk();
    }

    public function test_dotacion_de_jefatura_respeta_su_alcance_por_responsabilidades(): void
    {
        $fueraDeAlcance = UnidadOrganizacional::query()
            ->whereNotIn('id', [$this->unidadTitular->id, $this->unidadSubrogante->id])
            ->where('activo', true)
            ->firstOrFail();

        $this->vinculo($this->unidadTitular, 'Visible Equipamiento');
        $this->vinculo($this->unidadSubrogante, 'Visible Informática');
        $this->vinculo($fueraDeAlcance, 'No visible fuera de alcance');

        $this->actingAs($this->jefatura)
            ->get(route('admin.dotacion.index'))
            ->assertOk()
            ->assertSee('Visible Equipamiento')
            ->assertSee('Visible Informática')
            ->assertDontSee('No visible fuera de alcance');
    }

    public function test_jefatura_mas_solicitante_conserva_experiencia_de_solicitante(): void
    {
        $this->jefatura->assignRole('Solicitante');

        $this->actingAs($this->jefatura)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Gestiona y realiza seguimiento a tus solicitudes.')
            ->assertDontSee('Consulta la dotación y estructura organizacional de las unidades dentro de tu ámbito de operación.')
            ->assertSee('Trámites')
            ->assertSee('Nueva solicitud de reemplazo');
    }

    public function test_solicitante_puro_conserva_su_dashboard_y_navegacion(): void
    {
        $persona = Persona::query()->create(['rut' => '76000002-2', 'nombres' => 'Solicitante', 'apellido_paterno' => 'Puro', 'active' => true]);
        $solicitante = User::factory()->create(['persona_id' => $persona->id, 'active' => true, 'name' => 'Solicitante Puro']);
        $solicitante->assignRole('Solicitante');
        UnidadResponsable::query()->create([
            'unidad_organizacional_id' => $this->unidadSubrogante->id,
            'persona_id' => $persona->id,
            'tipo' => TipoResponsabilidad::SUBROGANTE,
            'vigente_desde' => today()->subMonth(),
            'puede_aprobar' => true,
            'created_by' => $solicitante->id,
        ]);

        $this->actingAs($solicitante)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Gestiona y realiza seguimiento a tus solicitudes.')
            ->assertSee('Trámites')
            ->assertSee('Nueva solicitud de reemplazo')
            ->assertDontSee('Consulta la dotación y estructura organizacional de las unidades dentro de tu ámbito de operación.');
    }

    private function responsabilidad(UnidadOrganizacional $unidad, TipoResponsabilidad $tipo): void
    {
        UnidadResponsable::query()->create([
            'unidad_organizacional_id' => $unidad->id,
            'persona_id' => $this->jefatura->persona_id,
            'tipo' => $tipo,
            'vigente_desde' => today()->subMonth(),
            'puede_aprobar' => true,
            'created_by' => $this->jefatura->id,
        ]);
    }

    private function vinculo(UnidadOrganizacional $unidad, string $cargo): void
    {
        PersonaUnidadVinculo::query()->create([
            'persona_id' => Persona::query()->create([
                'rut' => fake()->unique()->numerify('77######-#'),
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
            'created_by' => $this->jefatura->id,
        ]);
    }
}
