<?php

namespace Tests\Feature;

use App\Actions\HorasExtraordinarias\CargarPlanillaSirh;
use App\Actions\HorasExtraordinarias\CrearHorasExtraordinarias;
use App\Actions\HorasExtraordinarias\FinalizarRevisionHorasExtra;
use App\Actions\HorasExtraordinarias\GuardarBorradorHorasExtra;
use App\Actions\HorasExtraordinarias\RegistrarHorasExtra;
use App\Actions\HorasExtraordinarias\RevisarPlanillaSirh;
use App\Actions\Tramites\TransicionarTramite;
use App\Models\HorasExtraFuncionario;
use App\Models\Persona;
use App\Models\Tramite;
use App\Models\UnidadServicio;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PhaseSixOvertimeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        foreach ([['11111111-1', 'Ana'], ['22222222-2', 'Bruno'], ['44444444-4', 'Carla']] as [$rut, $name]) {
            Persona::query()->firstOrCreate(['rut' => $rut], ['nombres' => $name, 'apellido_paterno' => 'Ficticio', 'active' => true]);
        }
        Storage::fake('private');
    }

    public function test_creation_is_atomic_server_side_and_derives_leap_month(): void
    {
        $tramite = $this->create([1, 2], 2028, 2);
        $this->assertSame('HORAS_EXTRAORDINARIAS', $tramite->tipoTramite->codigo);
        $this->assertSame('BORRADOR', $tramite->estadoTramite->codigo);
        $this->assertSame('2028-02-01', $tramite->horasExtra->period_start->toDateString());
        $this->assertSame('2028-02-29', $tramite->horasExtra->period_end->toDateString());
        $this->assertCount(2, $tramite->horasExtra->funcionarios);
        $this->assertDatabaseHas('tramite_historial', ['tramite_id' => $tramite->id, 'action_code' => 'HORAS_EXTRA_FUNCIONARIO_AGREGADO']);
    }

    public function test_duplicate_participant_and_unauthorized_unit_are_rejected(): void
    {
        $this->expectException(ValidationException::class);
        app(CrearHorasExtraordinarias::class)->execute($this->unit(), 2026, 8, [1, 1], $this->jefe());
    }

    public function test_jefe_cannot_use_unit_outside_scope(): void
    {
        $unit = UnidadServicio::query()->whereKeyNot($this->unit()->id)->firstOrFail();
        $this->expectException(AuthorizationException::class);
        app(CrearHorasExtraordinarias::class)->execute($unit, 2026, 8, [Persona::query()->firstOrFail()->id], $this->jefe());
    }

    public function test_draft_can_change_period_unit_and_participants_and_logs_changes(): void
    {
        $tramite = $this->create([1]);
        $admin = $this->admin();
        $ids = Persona::query()->limit(2)->pluck('id')->all();
        app(GuardarBorradorHorasExtra::class)->execute($tramite, UnidadServicio::query()->skip(1)->firstOrFail(), 2027, 12, $ids, $admin);
        $this->assertSame(12, $tramite->fresh()->horasExtra->month);
        $this->assertCount(2, $tramite->fresh()->horasExtra->funcionarios);
    }

    public function test_send_uses_central_transition_and_locks_free_editing(): void
    {
        $tramite = $this->send($this->create([1]));
        $this->assertSame('ENVIADA_GESTION_PERSONAS', $tramite->estadoTramite->codigo);
        $this->assertDatabaseHas('tramite_historial', ['tramite_id' => $tramite->id, 'action_code' => 'ENVIAR_A_GESTION_PERSONAS']);
        $this->expectException(ValidationException::class);
        app(GuardarBorradorHorasExtra::class)->execute($tramite, $this->unit(), 2026, 9, [1], $this->jefe());
    }

    public function test_only_management_can_upload_and_only_pdf_is_accepted(): void
    {
        $participant = $this->send($this->create([1]))->horasExtra->funcionarios->first();
        try {
            app(CargarPlanillaSirh::class)->execute($participant, $this->pdf(), $this->jefe());
            $this->fail('Jefatura no debe cargar planillas.');
        } catch (AuthorizationException) {
        }
        $this->expectException(ValidationException::class);
        app(CargarPlanillaSirh::class)->execute($participant, UploadedFile::fake()->create('planilla.xlsx', 10, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'), $this->gp());
    }

    public function test_pdf_reuses_expediente_and_associates_correct_person(): void
    {
        $participant = $this->send($this->create([1]))->horasExtra->funcionarios->first();
        $planilla = app(CargarPlanillaSirh::class)->execute($participant, $this->pdf(), $this->gp());
        $this->assertSame('application/pdf', $planilla->adjunto->mime_type);
        $this->assertSame($participant->persona_id, $planilla->adjunto->persona_id);
        $this->assertSame($participant->tramiteHorasExtra->tramite_id, $planilla->adjunto->tramite_id);
        Storage::disk('private')->assertExists($planilla->adjunto->storage_path);
    }

    public function test_hours_are_calculated_in_minutes_and_zero_is_valid(): void
    {
        $participant = $this->send($this->create([1]))->horasExtra->funcionarios->first();
        app(CargarPlanillaSirh::class)->execute($participant, $this->pdf(), $this->gp());
        $saved = app(RegistrarHorasExtra::class)->execute($participant, 2, 30, 1, 15, $this->gp());
        $this->assertSame(150, $saved->daytime_minutes);
        $this->assertSame(75, $saved->night_festive_minutes);
        $this->assertSame(225, $saved->total_minutes);
        $zero = app(RegistrarHorasExtra::class)->execute($participant, 0, 0, 0, 0, $this->gp());
        $this->assertSame(0, $zero->total_minutes);
    }

    public function test_planillas_cannot_be_published_until_every_participant_is_complete(): void
    {
        $tramite = $this->send($this->create([1, 2]));
        $first = $tramite->horasExtra->funcionarios->first();
        $this->completeParticipant($first);
        $this->expectException(ValidationException::class);
        app(TransicionarTramite::class)->execute($tramite, 'PUBLICAR_PLANILLAS', $this->gp());
    }

    public function test_send_rejects_a_draft_without_participants(): void
    {
        $tramite = $this->create([1]);
        $tramite->horasExtra->funcionarios()->delete();
        $this->expectException(ValidationException::class);
        app(TransicionarTramite::class)->execute($tramite, 'ENVIAR_A_GESTION_PERSONAS', $this->jefe());
    }

    public function test_complete_planillas_can_be_published_and_jefe_starts_review(): void
    {
        $tramite = $this->readyForReview(2);
        $this->assertSame('PLANILLA_DISPONIBLE', $tramite->estadoTramite->codigo);
        $review = app(TransicionarTramite::class)->execute($tramite, 'INICIAR_REVISION_JEFATURA', $this->jefe());
        $this->assertSame('EN_REVISION_JEFATURA', $review->estadoTramite->codigo);
    }

    public function test_observation_is_required_and_reviews_are_historical(): void
    {
        $tramite = $this->inReview();
        $participant = $tramite->horasExtra->funcionarios->first();
        try {
            app(RevisarPlanillaSirh::class)->execute($participant, 'OBSERVADA', null, $this->jefe());
            $this->fail();
        } catch (ValidationException) {
        }
        $revision = app(RevisarPlanillaSirh::class)->execute($participant, 'OBSERVADA', 'Diferencia ficticia', $this->jefe());
        $this->assertSame('OBSERVADA', $participant->fresh()->review_status);
        $this->assertDatabaseHas('horas_extra_revisiones', ['id' => $revision->id, 'reviewed_by' => $this->jefe()->id]);
        $this->expectException(\LogicException::class);
        $revision->update(['observation' => 'No permitido']);
    }

    public function test_management_cannot_register_jefatura_decision_without_explicit_permission(): void
    {
        $tramite = $this->inReview();
        $this->expectException(AuthorizationException::class);
        app(RevisarPlanillaSirh::class)->execute($tramite->horasExtra->funcionarios->first(), 'CONFORME', null, $this->gp());
    }

    public function test_pending_blocks_finish_and_all_conforme_reaches_conforme(): void
    {
        $tramite = $this->inReview(2);
        app(RevisarPlanillaSirh::class)->execute($tramite->horasExtra->funcionarios->first(), 'CONFORME', null, $this->jefe());
        try {
            app(FinalizarRevisionHorasExtra::class)->execute($tramite, $this->jefe());
            $this->fail();
        } catch (ValidationException) {
        }
        app(RevisarPlanillaSirh::class)->execute($tramite->horasExtra->funcionarios->last(), 'CONFORME', null, $this->jefe());
        $done = app(FinalizarRevisionHorasExtra::class)->execute($tramite, $this->jefe());
        $this->assertSame('CONFORME', $done->estadoTramite->codigo);
    }

    public function test_observed_cycle_versions_pdf_resets_review_and_preserves_conforme_peer(): void
    {
        $tramite = $this->inReview(2);
        [$observed, $conforme] = $tramite->horasExtra->funcionarios->values()->all();
        app(RevisarPlanillaSirh::class)->execute($observed, 'OBSERVADA', 'Diferencia ficticia', $this->jefe());
        app(RevisarPlanillaSirh::class)->execute($conforme, 'CONFORME', null, $this->jefe());
        $tramite = app(FinalizarRevisionHorasExtra::class)->execute($tramite, $this->jefe());
        $this->assertSame('OBSERVADA', $tramite->estadoTramite->codigo);
        $tramite = app(TransicionarTramite::class)->execute($tramite, 'INICIAR_CORRECCION_SIRH', $this->gp());
        $old = $observed->planillaVigente;
        $new = app(CargarPlanillaSirh::class)->execute($observed, $this->pdf('corregida.pdf'), $this->gp());
        app(RegistrarHorasExtra::class)->execute($observed, 3, 0, 0, 0, $this->gp());
        $this->assertSame(2, $new->version);
        $this->assertFalse($old->fresh()->is_current);
        $this->assertSame('REEMPLAZADO', $old->adjunto->fresh()->status);
        $this->assertSame('PENDIENTE', $observed->fresh()->review_status);
        $this->assertSame('CONFORME', $conforme->fresh()->review_status);
        $available = app(TransicionarTramite::class)->execute($tramite, 'VOLVER_A_PLANILLA_DISPONIBLE', $this->gp());
        $this->assertSame('PLANILLA_DISPONIBLE', $available->estadoTramite->codigo);
        $this->assertDatabaseCount('horas_extra_revisiones', 2);
    }

    public function test_detail_shows_versions_and_future_tables_do_not_exist(): void
    {
        $tramite = $this->readyForReview();
        $this->actingAs($this->jefe())->get(route('tramites.show', $tramite))->assertOk()->assertSee('Planilla v1')->assertSee('Funcionarios y Planillas SIRH');
        foreach (['docdigital_registros'] as $table) {
            $this->assertFalse(Schema::hasTable($table));
        }
        $this->assertSame('varchar', Schema::getColumnType('horas_extra_funcionarios', 'review_status'));
        $this->assertSame('varchar', Schema::getColumnType('horas_extra_revisiones', 'result'));
    }

    private function create(array $positions = [1], int $year = 2026, int $month = 8): Tramite
    {
        $ids = Persona::query()->orderBy('id')->limit(count($positions))->pluck('id')->all();

        return app(CrearHorasExtraordinarias::class)->execute($this->unit(), $year, $month, $ids, $this->jefe())->load(['tipoTramite', 'estadoTramite', 'horasExtra.funcionarios']);
    }

    private function send(Tramite $tramite): Tramite
    {
        return app(TransicionarTramite::class)->execute($tramite, 'ENVIAR_A_GESTION_PERSONAS', $this->jefe())->load('horasExtra.funcionarios');
    }

    private function completeParticipant(HorasExtraFuncionario $participant): void
    {
        app(CargarPlanillaSirh::class)->execute($participant, $this->pdf(), $this->gp());
        app(RegistrarHorasExtra::class)->execute($participant, 1, 0, 0, 0, $this->gp());
    }

    private function readyForReview(int $count = 1): Tramite
    {
        $tramite = $this->send($this->create(range(1, $count)));
        foreach ($tramite->horasExtra->funcionarios as $participant) {
            $this->completeParticipant($participant);
        }

        return app(TransicionarTramite::class)->execute($tramite, 'PUBLICAR_PLANILLAS', $this->gp())->load('horasExtra.funcionarios.planillaVigente');
    }

    private function inReview(int $count = 1): Tramite
    {
        return app(TransicionarTramite::class)->execute($this->readyForReview($count), 'INICIAR_REVISION_JEFATURA', $this->jefe())->load('horasExtra.funcionarios.planillaVigente');
    }

    private function pdf(string $name = 'planilla.pdf'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, "%PDF-1.4\ncontenido ficticio\n%%EOF");
    }

    private function unit(): UnidadServicio
    {
        return $this->jefe()->unidadesHabilitadas()->firstOrFail();
    }

    private function jefe(): User
    {
        return User::query()->where('email', 'jefatura@example.test')->firstOrFail();
    }

    private function gp(): User
    {
        return tap(User::query()->firstOrCreate(['email' => 'gestion@example.test'], ['name' => 'Gestión Ficticia', 'password' => 'password', 'active' => true]), fn ($user) => $user->syncRoles('Gestión de Personas'));
    }

    private function admin(): User
    {
        return User::query()->where('email', 'admin@example.test')->firstOrFail();
    }
}
