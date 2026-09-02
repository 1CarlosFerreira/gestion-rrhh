<?php

namespace Tests\Feature;

use App\Actions\Reemplazos\AgregarCoberturaAusencia;
use App\Actions\Reemplazos\CerrarAusenciaReemplazable;
use App\Actions\Reemplazos\CrearReemplazo;
use App\Actions\Reemplazos\EnviarReemplazo;
use App\Actions\Reemplazos\GuardarBorradorReemplazo;
use App\Models\Estamento;
use App\Models\Persona;
use App\Models\Profesion;
use App\Models\TipoReemplazo;
use App\Models\Tramite;
use App\Models\User;
use App\Services\Reemplazos\CoberturaAusenciaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ReplacementAbsenceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_creation_accepts_four_dates_and_draft_may_remain_incomplete(): void
    {
        $draft = app(CrearReemplazo::class)->execute($this->unit(), $this->jefe());
        $this->assertNotNull($draft->reemplazo->ausencia);
        $this->assertNull($draft->reemplazo->ausencia->fecha_inicio);

        app(GuardarBorradorReemplazo::class)->execute($draft, [
            ...$this->sharedData(),
            'fecha_inicio_ausencia' => '2026-09-01', 'fecha_termino_ausencia' => '2026-09-30',
            'fecha_inicio' => '2026-09-05', 'fecha_termino' => '2026-09-12',
        ], $this->jefe());

        $draft->refresh();
        $this->assertSame('2026-09-01', $draft->reemplazo->ausencia->fecha_inicio->toDateString());
        $this->assertSame('2026-09-30', $draft->reemplazo->ausencia->fecha_termino->toDateString());
        $this->assertSame('2026-09-05', $draft->reemplazo->fecha_inicio->toDateString());
        $this->assertSame('2026-09-12', $draft->reemplazo->fecha_termino->toDateString());
        $this->actingAs($this->jefe())->get(route('reemplazos.create'))->assertSee('Usar el mismo periodo de ausencia');
    }

    public function test_send_requires_coverage_inside_absence_with_specific_messages(): void
    {
        $draft = $this->coverage('2026-09-01', '2026-09-30', '2026-08-31', '2026-10-01');

        try {
            app(EnviarReemplazo::class)->execute($draft, $this->jefe());
            $this->fail('Expected date validation.');
        } catch (ValidationException $exception) {
            $this->assertSame('La fecha de inicio del reemplazo no puede ser anterior al inicio de la ausencia.', $exception->errors()['fecha_inicio'][0]);
            $this->assertSame('La fecha de término del reemplazo no puede superar el término de la ausencia.', $exception->errors()['fecha_termino'][0]);
        }
        $this->assertSame('BORRADOR', $draft->fresh()->estadoTramite->codigo);
    }

    public function test_inclusive_days_and_available_intervals_include_weekends_and_holidays(): void
    {
        $first = $this->coverage('2026-09-01', '2026-09-30', '2026-09-01', '2026-09-10');
        app(EnviarReemplazo::class)->execute($first, $this->jefe());
        $second = app(AgregarCoberturaAusencia::class)->execute($first->reemplazo->ausencia, $this->jefe());
        $this->fillCoverage($second, '2026-09-15', '2026-09-25');
        app(EnviarReemplazo::class)->execute($second, $this->jefe());

        $summary = app(CoberturaAusenciaService::class)->summary($first->reemplazo->ausencia->fresh());
        $this->assertSame(30, $summary['total_dias']);
        $this->assertSame(21, $summary['dias_en_tramite']);
        $this->assertSame(9, $summary['dias_disponibles']);
        $this->assertSame([['2026-09-11', '2026-09-14'], ['2026-09-26', '2026-09-30']], collect($summary['intervalos_disponibles'])->map(fn ($period) => [$period['inicio']->toDateString(), $period['termino']->toDateString()])->all());
    }

    public function test_overlap_is_rejected_but_consecutive_periods_are_valid_and_drafts_do_not_reserve(): void
    {
        $first = $this->coverage('2026-09-01', '2026-09-30', '2026-09-01', '2026-09-10');
        app(EnviarReemplazo::class)->execute($first, $this->jefe());
        $second = app(AgregarCoberturaAusencia::class)->execute($first->reemplazo->ausencia, $this->jefe());
        $this->fillCoverage($second, '2026-09-10', '2026-09-15');

        $this->expectExceptionObject(ValidationException::withMessages(['fecha_inicio' => 'El periodo seleccionado se superpone con otra cobertura del mismo funcionario.']));
        app(EnviarReemplazo::class)->execute($second, $this->jefe());
    }

    public function test_consecutive_coverage_can_be_sent_and_each_coverage_has_its_own_tramite(): void
    {
        $first = $this->coverage('2026-09-01', '2026-09-30', '2026-09-01', '2026-09-10');
        app(EnviarReemplazo::class)->execute($first, $this->jefe());
        $second = app(AgregarCoberturaAusencia::class)->execute($first->reemplazo->ausencia, $this->jefe());
        $this->fillCoverage($second, '2026-09-11', '2026-09-20');
        app(EnviarReemplazo::class)->execute($second, $this->jefe());

        $this->assertNotSame($first->id, $second->id);
        $this->assertSame($first->reemplazo->ausencia_reemplazable_id, $second->reemplazo->ausencia_reemplazable_id);
        $this->assertSame(2, $first->reemplazo->ausencia->coberturas()->count());
    }

    public function test_summary_states_and_manual_close_are_derived_and_audited(): void
    {
        $first = $this->coverage('2026-09-01', '2026-09-30', '2026-09-01', '2026-09-10');
        $service = app(CoberturaAusenciaService::class);
        $this->assertSame('Sin cobertura', $service->summary($first->reemplazo->ausencia)['estado']);
        app(EnviarReemplazo::class)->execute($first, $this->jefe());
        $this->assertSame('Parcialmente cubierta', $service->summary($first->reemplazo->ausencia->fresh())['estado']);

        $closed = app(CerrarAusenciaReemplazable::class)->execute($first->reemplazo->ausencia, 'Cierre ficticio solicitado por jefatura.', $this->jefe());
        $this->assertSame('Cerrada', $service->summary($closed)['estado']);
        $this->assertDatabaseHas('ausencia_reemplazable_historial', ['ausencia_reemplazable_id' => $closed->id, 'action_code' => 'AUSENCIA_CERRADA']);
        $this->expectException(ValidationException::class);
        app(AgregarCoberturaAusencia::class)->execute($closed, $this->jefe());
    }

    public function test_full_period_is_completely_covered_and_other_unit_is_forbidden(): void
    {
        $coverage = $this->coverage('2026-09-01', '2026-09-30', '2026-09-01', '2026-09-30');
        app(EnviarReemplazo::class)->execute($coverage, $this->jefe());
        $summary = app(CoberturaAusenciaService::class)->summary($coverage->reemplazo->ausencia->fresh());
        $this->assertSame('Completamente cubierta', $summary['estado']);
        $this->assertSame(100.0, $summary['porcentaje']);
        $this->actingAs(User::factory()->create())->get(route('reemplazos.absences.show', $coverage->reemplazo->ausencia))->assertForbidden();
    }

    private function coverage(string $absenceStart, string $absenceEnd, string $start, string $end): Tramite
    {
        $tramite = app(CrearReemplazo::class)->execute($this->unit(), $this->jefe());
        app(GuardarBorradorReemplazo::class)->execute($tramite, [
            ...$this->sharedData(), 'fecha_inicio_ausencia' => $absenceStart, 'fecha_termino_ausencia' => $absenceEnd,
            'fecha_inicio' => $start, 'fecha_termino' => $end,
        ], $this->jefe());

        return $tramite->fresh();
    }

    private function fillCoverage(Tramite $tramite, string $start, string $end): void
    {
        app(GuardarBorradorReemplazo::class)->execute($tramite->load(['estadoTramite', 'reemplazo']), [
            'reemplazante_id' => Persona::query()->whereKeyNot($tramite->reemplazo->funcionario_id)->firstOrFail()->id,
            'estamento_id' => Estamento::query()->firstOrFail()->id, 'profesion_id' => Profesion::query()->firstOrFail()->id,
            'cargo_texto' => 'Cobertura ficticia', 'fecha_inicio' => $start, 'fecha_termino' => $end,
        ], $this->jefe());
    }

    private function sharedData(): array
    {
        $people = Persona::query()->limit(2)->get();

        return [
            'tipo_reemplazo_id' => TipoReemplazo::query()->firstOrFail()->id,
            'funcionario_id' => $people[0]->id, 'reemplazante_id' => $people[1]->id,
            'estamento_id' => Estamento::query()->firstOrFail()->id, 'profesion_id' => Profesion::query()->firstOrFail()->id,
            'cargo_texto' => 'Cargo ficticio', 'justificacion' => 'Ausencia ficticia para pruebas.',
        ];
    }

    private function jefe(): User
    {
        return User::query()->where('email', 'jefatura@example.test')->firstOrFail();
    }

    private function unit()
    {
        return $this->jefe()->unidadesHabilitadas()->firstOrFail();
    }
}
