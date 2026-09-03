<?php

namespace Tests\Feature;

use App\Models\EstadoTramite;
use App\Models\Persona;
use App\Models\TipoReemplazo;
use App\Models\TipoTramite;
use App\Models\Tramite;
use App\Models\TramiteReemplazo;
use App\Models\UnidadOrganizacional;
use App\Models\User;
use App\Services\Reemplazos\ReemplazoService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ReemplazosV2ANucleoTest extends TestCase
{
    use RefreshDatabase;

    private ReemplazoService $service;

    private TipoTramite $tipoTramite;

    private EstadoTramite $estadoActivo;

    private EstadoTramite $estadoExcluido;

    private TipoReemplazo $tipoReemplazo;

    private UnidadOrganizacional $unidad;

    private Persona $funcionario;

    private Persona $reemplazante;

    private User $actor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->service = app(ReemplazoService::class);
        $this->actor = User::factory()->create();
        $this->unidad = UnidadOrganizacional::query()->where('codigo', 'SDGADM-INF')->firstOrFail();
        $this->tipoTramite = TipoTramite::query()->create(['codigo' => 'REEMPLAZO_TEST', 'nombre' => 'Reemplazo prueba', 'activo' => true]);
        $this->estadoActivo = EstadoTramite::query()->create(['tipo_tramite_id' => $this->tipoTramite->id, 'codigo' => 'ACTIVO_TEST', 'nombre' => 'Activo', 'orden' => 1, 'es_inicial' => true]);
        $this->estadoExcluido = EstadoTramite::query()->create(['tipo_tramite_id' => $this->tipoTramite->id, 'codigo' => 'EXCLUIDO_TEST', 'nombre' => 'Excluido', 'orden' => 2]);
        $this->tipoReemplazo = TipoReemplazo::query()->create(['codigo' => 'TIPO_TEST', 'nombre' => 'Tipo prueba', 'activo' => true]);
        $this->funcionario = $this->persona('70000001-1', 'Funcionario');
        $this->reemplazante = $this->persona('70000002-2', 'Reemplazante');
    }

    public function test_tramite_belongs_to_organizational_unit_and_has_one_replacement_detail(): void
    {
        $tramite = $this->tramite();
        $detalle = $this->service->crear($tramite, $this->datos());
        $this->assertTrue($tramite->unidadOrganizacional->is($this->unidad));
        $this->assertTrue($tramite->reemplazo->is($detalle));
        $this->assertTrue($detalle->funcionario->is($this->funcionario));
        $this->assertTrue($detalle->reemplazante->is($this->reemplazante));
        $this->expectException(ValidationException::class);
        $this->service->crear($tramite, $this->datos());
    }

    public function test_schema_has_no_multiple_coverage_structure(): void
    {
        $this->assertTrue(Schema::hasTable('tramite_reemplazos'));
        $this->assertFalse(Schema::hasTable('reemplazo_coberturas'));
        foreach (['fecha_funcionario_desde', 'fecha_funcionario_hasta', 'fecha_reemplazante_desde', 'fecha_reemplazante_hasta'] as $column) {
            $this->assertTrue(Schema::hasColumn('tramite_reemplazos', $column));
        }
    }

    public function test_complete_and_partial_coverage_are_valid_and_calculated_inclusively(): void
    {
        $completo = $this->service->crear($this->tramite(), $this->datos());
        $parcial = $this->service->crear($this->tramite(), $this->datos(['funcionario_id' => $this->persona('70000003-3', 'Otra')->id, 'fecha_reemplazante_desde' => '2026-09-05', 'fecha_reemplazante_hasta' => '2026-09-25']));
        $this->assertSame(30, $completo->diasFuncionario());
        $this->assertSame(30, $completo->diasReemplazante());
        $this->assertSame(0, $completo->diasSinCobertura());
        $this->assertTrue($completo->coberturaTotal());
        $this->assertFalse($completo->coberturaParcial());
        $this->assertSame(30, $parcial->diasFuncionario());
        $this->assertSame(21, $parcial->diasReemplazante());
        $this->assertSame(9, $parcial->diasSinCobertura());
        $this->assertFalse($parcial->coberturaTotal());
        $this->assertTrue($parcial->coberturaParcial());
    }

    public function test_partial_coverage_at_each_boundary_is_valid(): void
    {
        $inicio = $this->service->crear($this->tramite(), $this->datos(['funcionario_id' => $this->persona('70000004-4', 'Inicio')->id, 'fecha_reemplazante_desde' => '2026-09-05']));
        $fin = $this->service->crear($this->tramite(), $this->datos(['funcionario_id' => $this->persona('70000005-5', 'Fin')->id, 'fecha_reemplazante_hasta' => '2026-09-20']));
        $this->assertTrue($inicio->coberturaParcial());
        $this->assertTrue($fin->coberturaParcial());
    }

    public function test_rejects_dates_outside_absence_and_inverted_periods(): void
    {
        foreach ([
            ['fecha_reemplazante_desde' => '2026-08-31'],
            ['fecha_reemplazante_hasta' => '2026-10-01'],
            ['fecha_reemplazante_desde' => '2026-09-20', 'fecha_reemplazante_hasta' => '2026-09-10'],
            ['fecha_funcionario_desde' => '2026-09-30', 'fecha_funcionario_hasta' => '2026-09-01'],
        ] as $cambio) {
            try {
                $this->service->validar($this->datos($cambio));
                $this->fail('El período inválido debió ser rechazado.');
            } catch (ValidationException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_detects_inclusive_overlap_allows_consecutive_periods_and_excludes_itself(): void
    {
        $detalle = $this->service->crear($this->tramite(), $this->datos(['fecha_funcionario_hasta' => '2026-09-15', 'fecha_reemplazante_hasta' => '2026-09-15']));
        $this->assertTrue($this->service->existeSuperposicion($this->funcionario->id, '2026-09-15', '2026-09-20'));
        $this->assertFalse($this->service->existeSuperposicion($this->funcionario->id, '2026-09-16', '2026-09-30'));
        $this->assertFalse($this->service->existeSuperposicion($this->funcionario->id, '2026-09-01', '2026-09-15', $detalle->id));
        $this->assertInstanceOf(TramiteReemplazo::class, $this->service->crear($this->tramite(), $this->datos(['fecha_funcionario_desde' => '2026-09-16', 'fecha_reemplazante_desde' => '2026-09-16'])));
    }

    public function test_overlap_accepts_a_future_workflow_state_filter(): void
    {
        $tramite = $this->tramite($this->estadoExcluido);
        $this->service->crear($tramite, $this->datos());
        $soloActivos = fn (Builder $query) => $query->where('estado_tramite_id', $this->estadoActivo->id);
        $this->assertFalse($this->service->existeSuperposicion($this->funcionario->id, '2026-09-10', '2026-09-20', null, $soloActivos));
    }

    private function datos(array $cambios = []): array
    {
        return [...['funcionario_id' => $this->funcionario->id, 'reemplazante_id' => $this->reemplazante->id, 'tipo_reemplazo_id' => $this->tipoReemplazo->id, 'fecha_funcionario_desde' => '2026-09-01', 'fecha_funcionario_hasta' => '2026-09-30', 'fecha_reemplazante_desde' => '2026-09-01', 'fecha_reemplazante_hasta' => '2026-09-30', 'justificacion' => 'Necesidad de continuidad operacional.'], ...$cambios];
    }

    private function tramite(?EstadoTramite $estado = null): Tramite
    {
        return Tramite::query()->create(['public_id' => (string) Str::ulid(), 'codigo' => 'TR-'.$this->actor->id.'-'.Str::random(8), 'tipo_tramite_id' => $this->tipoTramite->id, 'estado_tramite_id' => ($estado ?? $this->estadoActivo)->id, 'unidad_organizacional_id' => $this->unidad->id, 'created_by' => $this->actor->id]);
    }

    private function persona(string $rut, string $nombre): Persona
    {
        return Persona::query()->create(['rut' => $rut, 'nombres' => $nombre, 'active' => true]);
    }
}
