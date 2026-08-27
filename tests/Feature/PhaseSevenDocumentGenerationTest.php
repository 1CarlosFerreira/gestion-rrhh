<?php

namespace Tests\Feature;

use App\Actions\HorasExtraordinarias\CargarPlanillaSirh;
use App\Actions\HorasExtraordinarias\CrearHorasExtraordinarias;
use App\Actions\HorasExtraordinarias\FinalizarRevisionHorasExtra;
use App\Actions\HorasExtraordinarias\GenerarInformeTecnicoHorasExtra;
use App\Actions\HorasExtraordinarias\GuardarInformeTecnicoHorasExtra;
use App\Actions\HorasExtraordinarias\RegistrarHorasExtra;
use App\Actions\HorasExtraordinarias\RevisarPlanillaSirh;
use App\Actions\Reemplazos\CrearReemplazo;
use App\Actions\Tramites\TransicionarTramite;
use App\Models\DocumentoPlantilla;
use App\Models\Persona;
use App\Models\Tramite;
use App\Models\UnidadServicio;
use App\Models\User;
use App\Support\Documentos\DuracionMinutos;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class PhaseSevenDocumentGenerationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Storage::fake('private');
        for ($i = 1; $i <= 12; $i++) {
            Persona::query()->firstOrCreate(['rut' => sprintf('%08d-%d', 50000000 + $i, $i % 10)], ['nombres' => 'Persona '.$i, 'apellido_paterno' => 'Ficticia', 'active' => true]);
        }
        $unit = $this->unit();
        Persona::query()->each(fn (Persona $persona) => $persona->vinculos()->firstOrCreate(['unidad_servicio_id' => $unit->id], ['status' => 'ACTIVO']));
    }

    public function test_real_template_is_registered_without_replacement_template(): void
    {
        $template = DocumentoPlantilla::query()->sole();
        $this->assertSame('HE_INFORME_TECNICO', $template->codigo);
        $this->assertSame(1, $template->version);
        $this->assertSame(hash_file('sha256', base_path($template->template_path)), $template->sha256);
        $this->assertSame('HORAS_EXTRAORDINARIAS', $template->tipoTramite->codigo);
        $this->assertDatabaseMissing('documento_plantillas', ['codigo' => 'REEMPLAZO']);
    }

    public function test_duration_helper_never_uses_visible_string_for_calculation(): void
    {
        $this->assertSame('02:05', DuracionMinutos::asText(125));
        $this->assertEqualsWithDelta(125 / 1440, DuracionMinutos::asExcelTime(125), 0.0000001);
    }

    public function test_report_can_only_be_prepared_for_conforme_overtime(): void
    {
        $draft = $this->draft(1);
        $this->expectException(ValidationException::class);
        app(GuardarInformeTecnicoHorasExtra::class)->execute($draft, $this->reportData(), $this->jefe());
    }

    public function test_report_table_only_contains_its_own_fields_and_server_actor(): void
    {
        $tramite = $this->conforme(1);
        $report = app(GuardarInformeTecnicoHorasExtra::class)->execute($tramite, $this->reportData() + ['prepared_by' => $this->gp()->id], $this->jefe());
        $this->assertSame($this->jefe()->id, $report->prepared_by);
        foreach (['nombres', 'rut', 'period_start', 'daytime_minutes'] as $column) {
            $this->assertFalse(Schema::hasColumn('horas_extra_informes_tecnicos', $column));
        }
    }

    public function test_generation_creates_valid_private_xlsx_adjunto_snapshot_and_transition(): void
    {
        $templateHash = hash_file('sha256', base_path('resources/templates/horas-extra/informe_tecnico_horas_extra_v1.xlsx'));
        $tramite = $this->prepared(1);
        $document = app(GenerarInformeTecnicoHorasExtra::class)->execute($tramite, $this->jefe());
        $this->assertSame(1, $document->version);
        $this->assertSame($this->jefe()->id, $document->generated_by);
        $this->assertSame('INFORME_TECNICO', $document->tipoDocumento->codigo);
        $this->assertSame('INFORME_TECNICO_GENERADO', $tramite->fresh()->estadoTramite->codigo);
        Storage::disk('private')->assertExists($document->adjunto->storage_path);
        $this->assertSame(hash_file('sha256', Storage::disk('private')->path($document->adjunto->storage_path)), $document->adjunto->sha256);
        $this->assertSame($templateHash, hash_file('sha256', base_path('resources/templates/horas-extra/informe_tecnico_horas_extra_v1.xlsx')));
        $book = IOFactory::load(Storage::disk('private')->path($document->adjunto->storage_path));
        $this->assertSame(1, $book->getSheetCount());
        $this->assertSame($tramite->horasExtra->funcionarios->first()->persona->nombre_completo, $book->getActiveSheet()->getCell('B13')->getValue());
        $this->assertSame('[hh]:mm', $book->getActiveSheet()->getStyle('H13')->getNumberFormat()->getFormatCode());
        $this->assertDatabaseHas('tramite_historial', ['tramite_id' => $tramite->id, 'action_code' => 'DOCUMENTO_GENERADO']);
    }

    public function test_multiple_participants_expand_template_without_truncation(): void
    {
        $tramite = $this->prepared(12);
        $document = app(GenerarInformeTecnicoHorasExtra::class)->execute($tramite, $this->jefe());
        $book = IOFactory::load(Storage::disk('private')->path($document->adjunto->storage_path));
        $sheet = $book->getActiveSheet();
        $this->assertSame(12, $tramite->horasExtra->funcionarios->count());
        $this->assertNotSame('', $sheet->getCell('B24')->getValue());
        $this->assertContains('B24:G24', $sheet->getMergeCells());
        $this->assertSame($sheet->getRowDimension(22)->getRowHeight(), $sheet->getRowDimension(24)->getRowHeight());
        $this->assertStringContainsString('A1:I45', $sheet->getPageSetup()->getPrintArea());
    }

    public function test_regeneration_keeps_bytes_and_replaces_previous_records(): void
    {
        $tramite = $this->prepared(1);
        $first = app(GenerarInformeTecnicoHorasExtra::class)->execute($tramite, $this->jefe());
        $firstPath = $first->adjunto->storage_path;
        $second = app(GenerarInformeTecnicoHorasExtra::class)->execute($tramite->fresh(), $this->jefe());
        $this->assertSame(2, $second->version);
        $this->assertSame('REEMPLAZADO', $first->fresh()->status);
        $this->assertSame('REEMPLAZADO', $first->adjunto->fresh()->status);
        Storage::disk('private')->assertExists($firstPath);
        Storage::disk('private')->assertExists($second->adjunto->storage_path);
        $this->assertDatabaseHas('tramite_historial', ['tramite_id' => $tramite->id, 'action_code' => 'DOCUMENTO_REGENERADO']);
    }

    public function test_generation_failure_does_not_transition_and_permission_is_required(): void
    {
        $tramite = $this->prepared(1);
        $tramite->horasExtra->funcionarios()->first()->update(['review_status' => 'PENDIENTE']);
        try {
            app(GenerarInformeTecnicoHorasExtra::class)->execute($tramite, $this->jefe());
            $this->fail();
        } catch (ValidationException) {
        }
        $this->assertSame('CONFORME', $tramite->fresh()->estadoTramite->codigo);
        $this->expectException(AuthorizationException::class);
        app(GenerarInformeTecnicoHorasExtra::class)->execute($tramite, $this->gp());
    }

    public function test_replacement_cannot_use_missing_official_template(): void
    {
        $replacement = app(CrearReemplazo::class)->execute($this->unit(), $this->jefe());
        $this->expectException(ValidationException::class);
        app(GenerarInformeTecnicoHorasExtra::class)->execute($replacement, $this->jefe());
    }

    public function test_no_docdigital_pdf_or_internal_signature_is_created(): void
    {
        $document = app(GenerarInformeTecnicoHorasExtra::class)->execute($this->prepared(1), $this->jefe());
        $this->assertStringEndsWith('.xlsx', $document->adjunto->stored_name);
        $this->assertTrue(Schema::hasTable('docdigital_registros'));
        foreach (['firma', 'signature', 'certificado'] as $column) {
            $this->assertFalse(Schema::hasColumn('documentos_generados', $column));
        }
    }

    private function prepared(int $count): Tramite
    {
        $tramite = $this->conforme($count);
        app(GuardarInformeTecnicoHorasExtra::class)->execute($tramite, $this->reportData(), $this->jefe());

        return $tramite->fresh()->load('horasExtra.funcionarios');
    }

    private function conforme(int $count): Tramite
    {
        $tramite = $this->draft($count);
        $tramite = app(TransicionarTramite::class)->execute($tramite, 'ENVIAR_A_GESTION_PERSONAS', $this->jefe())->load('horasExtra.funcionarios');
        foreach ($tramite->horasExtra->funcionarios as $participant) {
            app(CargarPlanillaSirh::class)->execute($participant, UploadedFile::fake()->createWithContent('planilla.pdf', "%PDF-1.4\nficticio\n%%EOF"), $this->gp());
            app(RegistrarHorasExtra::class)->execute($participant, 2, 5, 1, 0, $this->gp());
        }
        $tramite = app(TransicionarTramite::class)->execute($tramite, 'PUBLICAR_PLANILLAS', $this->gp());
        $tramite = app(TransicionarTramite::class)->execute($tramite, 'INICIAR_REVISION_JEFATURA', $this->jefe())->load('horasExtra.funcionarios.planillaVigente');
        foreach ($tramite->horasExtra->funcionarios as $participant) {
            app(RevisarPlanillaSirh::class)->execute($participant, 'CONFORME', null, $this->jefe());
        }

        return app(FinalizarRevisionHorasExtra::class)->execute($tramite, $this->jefe())->load('horasExtra.funcionarios');
    }

    private function draft(int $count): Tramite
    {
        return app(CrearHorasExtraordinarias::class)->execute($this->unit(), 2026, 8, Persona::query()->orderBy('id')->limit($count)->pluck('id')->all(), $this->jefe())->load(['tipoTramite', 'estadoTramite', 'horasExtra.funcionarios']);
    }

    private function reportData(): array
    {
        return ['horario_diurno' => true, 'horario_festivo' => true, 'retribucion_tiempo' => true, 'retribucion_dinero' => false, 'justificacion_tecnica' => 'Justificación técnica completamente ficticia.', 'medidas_control' => 'Control mediante planilla ficticia y revisión de jefatura.'];
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
}
