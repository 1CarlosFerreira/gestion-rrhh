<?php

namespace Tests\Feature;

use App\Enums\TipoResponsabilidad;
use App\Models\CalidadContractual;
use App\Models\Estamento;
use App\Models\Persona;
use App\Models\TipoUnidadOrganizacional;
use App\Models\UnidadOrganizacional;
use App\Models\UnidadResponsable;
use App\Models\User;
use App\Services\Responsabilidades\ResponsabilidadInstitucionalService;
use Database\Seeders\RolesPermisosSeeder;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DotacionResponsabilidadCoordinadaTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Persona $persona;

    private UnidadOrganizacional $unidad;

    private Estamento $estamento;

    private CalidadContractual $calidad;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesPermisosSeeder::class);
        $this->admin = User::factory()->create(['active' => true]);
        $this->admin->assignRole('Administrador');
        $this->persona = Persona::query()->create(['rut' => '11111111-1', 'nombres' => 'Persona', 'active' => true]);
        $tipo = TipoUnidadOrganizacional::query()->create(['codigo' => 'UNIDAD', 'nombre' => 'Unidad', 'activo' => true]);
        $this->unidad = UnidadOrganizacional::query()->create(['codigo' => 'U1', 'nombre' => 'Unidad Uno', 'tipo_unidad_organizacional_id' => $tipo->id, 'activo' => true]);
        $this->estamento = Estamento::query()->create(['codigo' => 'EST', 'nombre' => 'Estamento', 'activo' => true]);
        $this->calidad = CalidadContractual::query()->create(['codigo' => 'CAL', 'nombre' => 'Calidad', 'activo' => true, 'orden' => 1]);
    }

    public function test_funcionario_crea_solamente_vinculo_de_dotacion(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.dotacion.store'), $this->datos(['responsabilidad_tipo' => 'FUNCIONARIO']))
            ->assertRedirect();

        $this->assertDatabaseCount('persona_unidad_vinculos', 1);
        $this->assertDatabaseCount('unidad_responsables', 0);
    }

    public function test_formulario_ofrece_tipos_y_campos_de_vigencia_con_funcionario_por_defecto(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.dotacion.create', ['persona_id' => $this->persona->id]))
            ->assertOk()
            ->assertSee('Progreso del proceso administrativo')
            ->assertSee('type="hidden" name="persona_id" value="'.$this->persona->id.'"', false)
            ->assertDontSee('<select id="persona_id"', false)
            ->assertSee('Antecedentes laborales')
            ->assertSee('Guardar vínculo y continuar →')
            ->assertSee('Responsabilidad en la unidad')
            ->assertSee('Funcionario')
            ->assertSee('Titular')
            ->assertSee('Subrogante')
            ->assertSee('Responsabilidad institucional')
            ->assertSee('Hasta (opcional)')
            ->assertSee('value="FUNCIONARIO" checked', false);

        $input = $this->inputPorNombre($response->getContent(), 'vigente_desde');
        $this->assertSame('date', $input->getAttribute('type'));
        $this->assertSame('vinculoDesde', $input->getAttribute('x-model'));
        $this->assertTrue($input->hasAttribute('required'));
    }

    public function test_otro_error_de_validacion_conserva_vigente_desde_en_el_input_visible(): void
    {
        $fecha = '2026-09-18';

        $this->actingAs($this->admin)
            ->from(route('admin.dotacion.create', ['persona_id' => $this->persona->id]))
            ->post(route('admin.dotacion.store'), $this->datos([
                'cargo_funcion' => '',
                'vigente_desde' => $fecha,
                'responsabilidad_tipo' => 'TITULAR',
                'responsabilidad_desde' => $fecha,
            ]))
            ->assertSessionHasErrors('cargo_funcion');

        $response = $this->actingAs($this->admin)
            ->get(route('admin.dotacion.create', ['persona_id' => $this->persona->id]))
            ->assertOk();

        $input = $this->inputPorNombre($response->getContent(), 'vigente_desde');
        $this->assertSame($fecha, $input->getAttribute('value'));
        $this->assertDatabaseCount('persona_unidad_vinculos', 0);
        $this->assertDatabaseCount('unidad_responsables', 0);
    }

    public function test_error_de_validacion_muestra_el_error_y_conserva_todos_los_campos(): void
    {
        $datos = $this->datos([
            'cargo_funcion' => 'Cargo ingresado por usuario',
            'grado_eus' => 12,
            'vigente_desde' => '',
            'vigente_hasta' => '2026-12-31',
            'observacion' => 'Observación que debe conservarse',
            'responsabilidad_tipo' => 'TITULAR',
            'responsabilidad_desde' => '2026-09-18',
            'responsabilidad_hasta' => '2026-10-31',
            'responsabilidad_desde_editada' => '1',
        ]);

        $response = $this->actingAs($this->admin)
            ->from(route('admin.dotacion.create', ['persona_id' => $this->persona->id]))
            ->post(route('admin.dotacion.store'), $datos);

        $response
            ->assertRedirect(route('admin.dotacion.create', ['persona_id' => $this->persona->id]))
            ->assertSessionHasErrors('vigente_desde')
            ->assertSessionHas('_old_input', function (array $old) use ($datos): bool {
                foreach ($datos as $campo => $valor) {
                    if (($old[$campo] ?? null) != $valor) {
                        return false;
                    }
                }

                return true;
            });

        $this->actingAs($this->admin)
            ->get(route('admin.dotacion.create', ['persona_id' => $this->persona->id]))
            ->assertOk()
            ->assertSee('No fue posible guardar el vínculo. Revisa los campos indicados.')
            ->assertSee('Cargo ingresado por usuario')
            ->assertSee('value="12"', false)
            ->assertSee('value="2026-12-31"', false)
            ->assertSee('Observación que debe conservarse')
            ->assertSee('value="TITULAR" checked', false)
            ->assertSee('2026-09-18')
            ->assertSee('value="2026-10-31"', false);

        $this->assertDatabaseCount('persona_unidad_vinculos', 0);
        $this->assertDatabaseCount('unidad_responsables', 0);
    }

    public function test_desde_laboral_sincroniza_responsable_desde_hasta_que_se_edita_manualmente(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.dotacion.create', ['persona_id' => $this->persona->id]))
            ->assertOk()
            ->assertSee('responsabilidadDesdeModificada', false)
            ->assertSee("if (responsabilidadTipo !== 'FUNCIONARIO' && ! responsabilidadDesdeModificada) responsabilidadDesde = vinculoDesde", false)
            ->assertSee('x-on:input="responsabilidadDesdeModificada = true"', false);
    }

    public function test_alta_exitosa_crea_vinculo_y_responsabilidad_antes_de_redirigir_a_continuidad(): void
    {
        $response = $this->actingAs($this->admin)
            ->post(route('admin.dotacion.store'), $this->datos([
                'responsabilidad_tipo' => 'TITULAR',
                'responsabilidad_desde' => '2026-01-15',
            ]));

        $vinculo = $this->persona->vinculosDotacion()->sole();
        $responsabilidad = UnidadResponsable::query()->sole();

        $this->assertSame($vinculo->persona_id, $responsabilidad->persona_id);
        $this->assertSame($vinculo->unidad_organizacional_id, $responsabilidad->unidad_organizacional_id);
        $response->assertRedirect(route('admin.dotacion.continue', [
            'vinculo' => $vinculo,
            'responsabilidad_id' => $responsabilidad->id,
        ]));

        $this->actingAs($this->admin)
            ->get(route('admin.personas.show', $this->persona))
            ->assertOk()
            ->assertSee('Responsabilidades institucionales')
            ->assertSee('Titular')
            ->assertSee('15/01/2026 → Actualidad');
    }

    public function test_titular_crea_vinculo_y_responsabilidad_con_vigencia_propia_y_aprobacion(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.dotacion.store'), $this->datos([
                'responsabilidad_tipo' => 'TITULAR',
                'responsabilidad_desde' => '2026-02-01',
                'responsabilidad_hasta' => '2026-02-10',
            ]))
            ->assertRedirect();

        $this->assertDatabaseCount('persona_unidad_vinculos', 1);
        $responsabilidad = UnidadResponsable::query()->sole();
        $this->assertSame(TipoResponsabilidad::TITULAR, $responsabilidad->tipo);
        $this->assertTrue($responsabilidad->puede_aprobar);
        $this->assertSame('2026-02-01', $responsabilidad->vigente_desde->toDateString());
        $this->assertSame('2026-02-10', $responsabilidad->vigente_hasta->toDateString());
    }

    public function test_subrogante_crea_vinculo_y_responsabilidad_con_aprobacion(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.dotacion.store'), $this->datos([
                'responsabilidad_tipo' => 'SUBROGANTE',
                'responsabilidad_desde' => '2026-01-01',
            ]))
            ->assertRedirect();

        $responsabilidad = UnidadResponsable::query()->sole();
        $this->assertSame(TipoResponsabilidad::SUBROGANTE, $responsabilidad->tipo);
        $this->assertTrue($responsabilidad->puede_aprobar);
        $this->assertNull($responsabilidad->vigente_hasta);
    }

    public function test_responsabilidad_no_puede_comenzar_antes_del_vinculo_laboral(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.dotacion.store'), $this->datos([
                'responsabilidad_tipo' => 'TITULAR',
                'responsabilidad_desde' => '2025-12-31',
            ]))
            ->assertSessionHasErrors('responsabilidad_desde');

        $this->assertDatabaseCount('persona_unidad_vinculos', 0);
        $this->assertDatabaseCount('unidad_responsables', 0);
    }

    public function test_responsabilidad_no_puede_terminar_despues_del_vinculo_y_hace_rollback(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.dotacion.store'), $this->datos([
                'vigente_hasta' => '2026-01-31',
                'responsabilidad_tipo' => 'TITULAR',
                'responsabilidad_desde' => '2026-01-10',
                'responsabilidad_hasta' => '2026-02-01',
            ]))
            ->assertSessionHasErrors('responsabilidad_hasta');

        $this->assertDatabaseCount('persona_unidad_vinculos', 0);
        $this->assertDatabaseCount('unidad_responsables', 0);
    }

    public function test_solapamiento_existente_de_responsabilidad_rechaza_y_revierte_el_vinculo(): void
    {
        $otraPersona = Persona::query()->create(['rut' => '22222222-2', 'nombres' => 'Titular', 'active' => true]);
        app(ResponsabilidadInstitucionalService::class)->crear([
            'unidad_organizacional_id' => $this->unidad->id,
            'persona_id' => $otraPersona->id,
            'tipo' => TipoResponsabilidad::TITULAR->value,
            'vigente_desde' => '2026-01-01',
            'vigente_hasta' => null,
            'puede_aprobar' => true,
            'observacion' => null,
        ], $this->admin);

        $this->actingAs($this->admin)
            ->post(route('admin.dotacion.store'), $this->datos([
                'responsabilidad_tipo' => 'TITULAR',
                'responsabilidad_desde' => '2026-02-01',
            ]))
            ->assertSessionHasErrors('vigente_desde');

        $this->assertDatabaseCount('persona_unidad_vinculos', 0);
        $this->assertDatabaseCount('unidad_responsables', 1);
    }

    private function datos(array $cambios = []): array
    {
        return [...[
            'persona_id' => $this->persona->id,
            'unidad_organizacional_id' => $this->unidad->id,
            'estamento_id' => $this->estamento->id,
            'profesion_id' => null,
            'calidad_contractual_id' => $this->calidad->id,
            'cargo_funcion' => 'Cargo base',
            'grado_eus' => null,
            'vigente_desde' => '2026-01-01',
            'vigente_hasta' => null,
            'origen' => 'MANUAL',
            'observacion' => null,
            'responsabilidad_tipo' => 'FUNCIONARIO',
            'responsabilidad_desde' => null,
            'responsabilidad_hasta' => null,
        ], ...$cambios];
    }

    private function inputPorNombre(string $html, string $nombre): \DOMElement
    {
        $document = new DOMDocument;
        @$document->loadHTML($html);
        $input = (new DOMXPath($document))->query("//input[@name='{$nombre}']")?->item(0);

        $this->assertInstanceOf(\DOMElement::class, $input, "No se renderizó el input {$nombre}.");

        return $input;
    }
}
