<?php

namespace Tests\Feature;

use App\Actions\Reemplazos\CrearReemplazo;
use App\Actions\Tramites\Adjuntos\CargarAdjunto;
use App\Models\Persona;
use App\Models\TipoDocumento;
use App\Models\TipoReemplazo;
use App\Models\TipoTramite;
use App\Models\Tramite;
use App\Models\TramiteAdjunto;
use App\Models\TramiteReemplazo;
use App\Models\UnidadServicio;
use App\Models\User;
use App\Support\Rut\Rut;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ReemplazoUnifiedCreationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private');
        $this->seed();
    }

    public function test_create_displays_the_complete_form_without_creating_a_root(): void
    {
        $before = Tramite::query()->count();

        $this->actingAs($this->jefe())->get(route('reemplazos.create'))->assertOk()
            ->assertSee('Nueva Solicitud de Reemplazo')
            ->assertSee('Estado: Nueva solicitud')
            ->assertSee('1. Origen del reemplazo')
            ->assertSee('2. Justificación')
            ->assertSee('3. Reemplazante propuesto')
            ->assertSee('x-data="JSON.parse(\'', false)
            ->assertSee('\\u0022unidad\\u0022:\\u0022\\u0022', false)
            ->assertSee('\\u0022nuevo\\u0022:false', false)
            ->assertDontSee("x-data='JSON.parse('", false)
            ->assertSee('id="buscar_reemplazante"', false)
            ->assertSee('>+ Agregar reemplazante que no está en la lista</button>', false)
            ->assertSee('4. Función y período propuesto')
            ->assertSee('5. Documentos de respaldo')
            ->assertSee('Adjunte los antecedentes necesarios para respaldar la solicitud.')
            ->assertSee('Guardar borrador')
            ->assertDontSee('Crear borrador');

        $this->assertDatabaseCount('tramites', $before);
    }

    public function test_create_only_exposes_authorized_units_and_their_current_active_staff(): void
    {
        $jefe = $this->jefe();
        $authorized = $this->unit();
        $unauthorized = UnidadServicio::query()->whereKeyNot($authorized->id)->firstOrFail();

        $response = $this->actingAs($jefe)->get(route('reemplazos.create'))->assertOk()
            ->assertSee($authorized->nombre)
            ->assertDontSee('<option value="'.$unauthorized->id.'">'.$unauthorized->nombre.'</option>', false)
            ->assertDontSee('data-unidad="'.$unauthorized->id.'"', false);

        $activePerson = Persona::query()->whereHas('vinculos', fn ($query) => $query->where('unidad_servicio_id', $authorized->id)->where('status', 'ACTIVO'))->firstOrFail();
        $response->assertSee('data-unidad="'.$authorized->id.'"', false)->assertSee($activePerson->nombre_completo);
    }

    public function test_complete_screen_creates_and_populates_both_records_server_side(): void
    {
        $unit = $this->unit();
        $employee = Persona::query()->whereHas('vinculos', fn ($query) => $query->where('unidad_servicio_id', $unit->id)->where('status', 'ACTIVO'))->firstOrFail();
        $replacement = Persona::query()->whereKeyNot($employee->id)->firstOrFail();
        $type = TipoReemplazo::query()->firstOrFail();

        $response = $this->actingAs($this->jefe())->post(route('reemplazos.store'), [
            'unidad_servicio_id' => $unit->id,
            'tipo_reemplazo_id' => $type->id,
            'funcionario_id' => $employee->id,
            'reemplazante_id' => $replacement->id,
            'justificacion' => 'Antecedente ficticio para guardar el borrador.',
            'fecha_inicio' => '2026-09-01',
            'fecha_termino' => '2026-09-30',
        ]);

        $tramite = Tramite::query()->latest('id')->firstOrFail();
        $response->assertRedirect(route('reemplazos.edit', $tramite))
            ->assertSessionHas('status', 'Borrador creado correctamente.');
        $this->assertSame('REEMPLAZO', $tramite->tipoTramite->codigo);
        $this->assertSame('BORRADOR', $tramite->estadoTramite->codigo);
        $this->assertSame($type->id, $tramite->reemplazo->tipo_reemplazo_id);
        $this->assertSame($employee->id, $tramite->reemplazo->funcionario_id);
        $this->assertDatabaseHas('tramite_historial', ['tramite_id' => $tramite->id, 'action_code' => 'TRAMITE_CREADO']);
    }

    public function test_failed_domain_validation_rolls_back_root_and_replacement(): void
    {
        $before = Tramite::query()->count();
        $replacementBefore = TramiteReemplazo::query()->count();
        $unit = $this->unit();
        $employeeFromAnotherUnit = Persona::query()->whereDoesntHave('vinculos', fn ($query) => $query->where('unidad_servicio_id', $unit->id))->firstOrFail();

        try {
            app(CrearReemplazo::class)->execute($unit, $this->jefe(), ['funcionario_id' => $employeeFromAnotherUnit->id]);
            $this->fail('Expected validation failure.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('tramites', $before);
            $this->assertDatabaseCount('tramite_reemplazos', $replacementBefore);
        }
    }

    public function test_client_cannot_choose_the_process_type(): void
    {
        $otherType = TipoTramite::query()->where('codigo', 'HORAS_EXTRAORDINARIAS')->firstOrFail();

        $this->actingAs($this->jefe())->post(route('reemplazos.store'), [
            'unidad_servicio_id' => $this->unit()->id,
            'tipo_tramite_id' => $otherType->id,
        ])->assertRedirect();

        $this->assertSame('REEMPLAZO', Tramite::query()->latest('id')->firstOrFail()->tipoTramite->codigo);
    }

    public function test_backend_rejects_same_person_by_ids(): void
    {
        $unit = $this->unit();
        $person = Persona::query()->whereHas('vinculos', fn ($query) => $query->where('unidad_servicio_id', $unit->id)->where('status', 'ACTIVO'))->firstOrFail();
        $before = Tramite::query()->count();

        $this->actingAs($this->jefe())->post(route('reemplazos.store'), [
            'unidad_servicio_id' => $unit->id,
            'funcionario_id' => $person->id,
            'reemplazante_id' => $person->id,
        ])->assertSessionHasErrors('reemplazante_id');

        try {
            app(CrearReemplazo::class)->execute($unit, $this->jefe(), ['funcionario_id' => $person->id, 'reemplazante_id' => $person->id]);
            $this->fail('Expected domain validation failure.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('reemplazante_id', $exception->errors());
        }
        $this->assertDatabaseCount('tramites', $before);
    }

    public function test_employee_is_excluded_from_existing_replacement_selector(): void
    {
        $unit = $this->unit();
        $person = Persona::query()->whereHas('vinculos', fn ($query) => $query->where('unidad_servicio_id', $unit->id)->where('status', 'ACTIVO'))->firstOrFail();
        $tramite = app(CrearReemplazo::class)->execute($unit, $this->jefe(), ['funcionario_id' => $person->id]);

        $this->actingAs($this->jefe())->get(route('reemplazos.edit', $tramite))->assertOk()
            ->assertDontSee('data-persona="'.$person->id.'"', false);
    }

    public function test_create_and_edit_render_complete_serialized_alpine_state(): void
    {
        $unit = $this->unit();
        $tramite = app(CrearReemplazo::class)->execute($unit, $this->jefe());

        $this->actingAs($this->jefe())->get(route('reemplazos.create'))->assertOk()
            ->assertSee('x-data="JSON.parse(\'', false)
            ->assertSee('\\u0022unidad\\u0022:\\u0022\\u0022', false)
            ->assertSee('\\u0022nuevo\\u0022:false', false)
            ->assertDontSee("x-data='JSON.parse('", false)
            ->assertSee('x-show="! nuevo" class="md:col-span-2"', false)
            ->assertSee('x-show="nuevo" x-cloak', false);

        $this->actingAs($this->jefe())->get(route('reemplazos.edit', $tramite))->assertOk()
            ->assertSee('x-data="JSON.parse(\'', false)
            ->assertSee('\\u0022unidad\\u0022:\\u0022'.$unit->id.'\\u0022', false)
            ->assertSee('\\u0022nuevo\\u0022:false', false)
            ->assertDontSee("x-data='JSON.parse('", false)
            ->assertSee('x-show="! nuevo" class="md:col-span-2"', false)
            ->assertSee('x-show="nuevo" x-cloak', false);
    }

    public function test_autocomplete_recalculates_candidates_when_employee_changes_and_clears_conflict(): void
    {
        $this->actingAs($this->jefe())->get(route('reemplazos.create'))->assertOk()
            ->assertSee("funcionario?.addEventListener('change'", false)
            ->assertSee("option.classList.toggle('hidden', ocultar)", false)
            ->assertDontSee('option.hidden = ocultar', false)
            ->assertSee('option.dataset.persona === funcionarioId', false)
            ->assertSee("reemplazante.value = ''", false)
            ->assertSee('El reemplazante seleccionado coincide con el funcionario a reemplazar y fue eliminado de la selección.')
            ->assertSee('reemplazante_conflicto', false);
    }

    public function test_new_replacement_rut_is_rejected_when_it_belongs_to_employee(): void
    {
        $unit = $this->unit();
        $person = Persona::query()->whereHas('vinculos', fn ($query) => $query->where('unidad_servicio_id', $unit->id)->where('status', 'ACTIVO'))->firstOrFail();
        $before = Tramite::query()->count();

        $this->actingAs($this->jefe())->post(route('reemplazos.store'), [
            'unidad_servicio_id' => $unit->id,
            'funcionario_id' => $person->id,
            'nuevo_reemplazante_rut' => $person->rut,
        ])->assertSessionHasErrors(['nuevo_reemplazante_rut' => 'Esta persona corresponde al funcionario que está siendo reemplazado y no puede registrarse como reemplazante.']);

        try {
            app(CrearReemplazo::class)->execute($unit, $this->jefe(), ['funcionario_id' => $person->id, 'nuevo_reemplazante_rut' => $person->rut]);
            $this->fail('Expected domain validation failure.');
        } catch (ValidationException $exception) {
            $this->assertSame('Esta persona corresponde al funcionario que está siendo reemplazado y no puede registrarse como reemplazante.', $exception->errors()['nuevo_reemplazante_rut'][0]);
        }
        $this->assertDatabaseCount('tramites', $before);
    }

    public function test_existing_rut_in_new_mode_reuses_person_without_duplicate(): void
    {
        $unit = $this->unit();
        $existing = Persona::query()->whereDoesntHave('vinculos', fn ($query) => $query->where('unidad_servicio_id', $unit->id))->firstOrFail();
        $peopleBefore = Persona::query()->count();
        $linksBefore = $existing->vinculos()->count();

        $this->actingAs($this->jefe())->post(route('reemplazos.store'), [
            'unidad_servicio_id' => $unit->id,
            'nuevo_reemplazante_rut' => $existing->rut,
        ])->assertRedirect();

        $this->assertDatabaseCount('personas', $peopleBefore);
        $this->assertSame($existing->id, Tramite::query()->latest('id')->firstOrFail()->reemplazo->reemplazante_id);
        $this->assertSame($linksBefore, $existing->vinculos()->count());
    }

    public function test_existing_and_new_modes_are_mutually_exclusive(): void
    {
        $existing = Persona::query()->firstOrFail();

        $this->actingAs($this->jefe())->post(route('reemplazos.store'), [
            'unidad_servicio_id' => $this->unit()->id,
            'reemplazante_id' => $existing->id,
            'nuevo_reemplazante_rut' => '11.111.111-1',
            'nuevo_reemplazante_nombres' => 'Persona Ficticia',
        ])->assertSessionHasErrors(['reemplazante_id', 'nuevo_reemplazante_rut']);

        $this->actingAs($this->jefe())->get(route('reemplazos.create'))->assertOk()
            ->assertSee("nuevo ? 'Cancelar' : '+ Agregar reemplazante que no está en la lista'", false)
            ->assertSee('x-show="! nuevo" class="md:col-span-2"', false)
            ->assertDontSee('x-show="! nuevo" x-cloak', false)
            ->assertSee('x-show="nuevo" x-cloak', false)
            ->assertSee('>+ Agregar reemplazante que no está en la lista</button>', false)
            ->assertSee('$refs.reemplazante.value = \'\'', false)
            ->assertSee('$refs.rut.value = \'\'', false)
            ->assertSee('$refs.nombres.value = \'\'', false)
            ->assertSee('$refs.paterno.value = \'\'', false)
            ->assertSee('$refs.materno.value = \'\'', false);
    }

    public function test_replacement_uses_one_filterable_combobox_and_exposes_context_for_prefill(): void
    {
        $person = Persona::query()->whereHas('vinculos')->with('vinculos')->firstOrFail();
        $link = $person->vinculos->first();

        $this->actingAs($this->jefe())->get(route('reemplazos.create'))->assertOk()
            ->assertSee('Seleccione o escriba RUT/nombre del reemplazante')
            ->assertSee('role="combobox"', false)
            ->assertSee('type="hidden" name="reemplazante_id" id="reemplazante_id"', false)
            ->assertDontSee('<select name="reemplazante_id"', false)
            ->assertSee("addEventListener('input'", false)
            ->assertSee("textContent.toLocaleLowerCase('es').includes(termino)", false)
            ->assertSee('data-persona="'.$person->id.'"', false)
            ->assertSee('data-estamento="'.$link->estamento_id.'"', false)
            ->assertSee('data-profesion="'.$link->profesion_id.'"', false)
            ->assertSee('document.getElementById(\'cargo_texto\').value', false)
            ->assertSee($person->nombre_completo.' · '.Rut::format($person->rut));
    }

    public function test_first_attachment_auto_saves_draft_and_uses_private_expediente(): void
    {
        $before = Tramite::query()->count();
        $content = "%PDF-1.4\nrespaldo ficticio";

        $response = $this->actingAs($this->jefe())->post(route('reemplazos.store'), [
            'unidad_servicio_id' => $this->unit()->id,
            'accion' => 'adjuntar',
            'archivo' => UploadedFile::fake()->createWithContent('respaldo.pdf', $content),
        ]);

        $tramite = Tramite::query()->latest('id')->firstOrFail();
        $adjunto = TramiteAdjunto::query()->latest('id')->firstOrFail();
        $response->assertRedirect(route('reemplazos.edit', $tramite))
            ->assertSessionHas('status', 'Borrador creado correctamente y documento adjuntado.');
        $this->assertDatabaseCount('tramites', $before + 1);
        $this->assertSame($tramite->id, $adjunto->tramite_id);
        $this->assertSame(hash('sha256', $content), $adjunto->sha256);
        $this->assertSame($this->jefe()->id, $adjunto->uploaded_by);
        $this->assertNull($adjunto->tipo_documento_id);
        Storage::disk('private')->assertExists($adjunto->storage_path);
    }

    public function test_saving_a_draft_with_a_file_attaches_it_and_preserves_its_classification(): void
    {
        $unit = $this->unit();
        $employee = Persona::query()->whereHas('vinculos', fn ($query) => $query->where('unidad_servicio_id', $unit->id)->where('status', 'ACTIVO'))->firstOrFail();
        $replacement = Persona::query()->whereKeyNot($employee->id)->firstOrFail();
        $documentType = TipoDocumento::query()->where('active', true)->firstOrFail();
        $content = "%PDF-1.4\nrespaldo clasificado";

        $response = $this->actingAs($this->jefe())->post(route('reemplazos.store'), [
            'unidad_servicio_id' => $unit->id,
            'tipo_reemplazo_id' => TipoReemplazo::query()->firstOrFail()->id,
            'funcionario_id' => $employee->id,
            'reemplazante_id' => $replacement->id,
            'justificacion' => 'Antecedente ficticio que debe persistir junto con el archivo.',
            'fecha_inicio' => '2026-09-01',
            'fecha_termino' => '2026-09-30',
            'accion' => 'guardar',
            'archivo' => UploadedFile::fake()->createWithContent('clasificado.pdf', $content),
            'tipo_documento_id' => $documentType->id,
        ]);

        $tramite = Tramite::query()->latest('id')->firstOrFail();
        $adjunto = $tramite->adjuntos()->firstOrFail();
        $response->assertRedirect(route('reemplazos.edit', $tramite))
            ->assertSessionHas('status', 'Borrador creado correctamente y documento adjuntado.');
        $this->assertSame($documentType->id, $adjunto->tipo_documento_id);
        $this->assertSame($this->jefe()->id, $adjunto->uploaded_by);
        $this->assertSame(hash('sha256', $content), $adjunto->sha256);
        $this->assertSame($employee->id, $tramite->reemplazo->funcionario_id);
        $this->assertSame($replacement->id, $tramite->reemplazo->reemplazante_id);
        Storage::disk('private')->assertExists($adjunto->storage_path);

        $this->actingAs($this->jefe())->get(route('reemplazos.edit', $tramite))->assertOk()
            ->assertSee('Documentos adjuntados')
            ->assertSee('clasificado.pdf')
            ->assertSee('Tipo: '.$documentType->nombre)
            ->assertSee('Versión: 1')
            ->assertSee('Estado: Activo')
            ->assertSee('Descargar')
            ->assertSee('+ Adjuntar otro documento');
    }

    public function test_editing_adds_another_document_without_replacing_and_versions_only_explicitly(): void
    {
        $tramite = app(CrearReemplazo::class)->execute($this->unit(), $this->jefe());
        $documentType = TipoDocumento::query()->where('active', true)->firstOrFail();

        $this->actingAs($this->jefe())->post(route('tramites.adjuntos.store', $tramite), [
            'archivo' => UploadedFile::fake()->createWithContent('primero.pdf', "%PDF-1.4\nprimero"),
            'tipo_documento_id' => $documentType->id,
        ])->assertSessionHas('status', 'Adjunto cargado.');
        $first = $tramite->adjuntos()->firstOrFail();

        $this->actingAs($this->jefe())->post(route('tramites.adjuntos.store', $tramite), [
            'archivo' => UploadedFile::fake()->createWithContent('segundo.pdf', "%PDF-1.4\nsegundo"),
        ])->assertSessionHas('status', 'Adjunto cargado.');

        $this->assertSame(2, $tramite->adjuntos()->count());
        $this->assertSame('ACTIVO', $first->fresh()->status);

        $this->actingAs($this->jefe())->post(route('tramites.adjuntos.version', [$tramite, $first]), [
            'archivo' => UploadedFile::fake()->createWithContent('primero-v2.pdf', "%PDF-1.4\nprimero v2"),
        ])->assertSessionHas('status', 'Nueva versión cargada.');

        $version = $tramite->adjuntos()->where('replaces_adjunto_id', $first->id)->firstOrFail();
        $this->assertSame(3, $tramite->adjuntos()->count());
        $this->assertSame('REEMPLAZADO', $first->fresh()->status);
        $this->assertSame(2, $version->version);
        $this->assertSame($documentType->id, $version->tipo_documento_id);
    }

    public function test_invalid_first_attachment_does_not_create_draft_or_file(): void
    {
        $before = Tramite::query()->count();

        $this->actingAs($this->jefe())->post(route('reemplazos.store'), [
            'accion' => 'adjuntar',
            'archivo' => UploadedFile::fake()->createWithContent('respaldo.pdf', "%PDF-1.4\ncontenido"),
        ])->assertSessionHasErrors(['unidad_servicio_id' => 'No fue posible adjuntar el documento porque faltan antecedentes necesarios para guardar el borrador.']);

        $this->actingAs($this->jefe())->post(route('reemplazos.store'), [
            'unidad_servicio_id' => $this->unit()->id,
            'accion' => 'adjuntar',
            'archivo' => UploadedFile::fake()->create('malware.exe', 10, 'application/x-msdownload'),
        ])->assertSessionHasErrors('archivo');

        $this->assertDatabaseCount('tramites', $before);
        $this->assertDatabaseCount('tramite_adjuntos', 0);
        $this->assertSame([], Storage::disk('private')->allFiles());
    }

    public function test_upload_failure_after_auto_save_keeps_draft(): void
    {
        $before = Tramite::query()->count();
        $this->mock(CargarAdjunto::class, fn ($mock) => $mock->shouldReceive('execute')->once()->andThrow(new \RuntimeException('Falla ficticia de almacenamiento')));

        $response = $this->actingAs($this->jefe())->post(route('reemplazos.store'), [
            'unidad_servicio_id' => $this->unit()->id,
            'accion' => 'adjuntar',
            'archivo' => UploadedFile::fake()->createWithContent('respaldo.pdf', "%PDF-1.4\ncontenido"),
        ]);

        $tramite = Tramite::query()->latest('id')->firstOrFail();
        $response->assertRedirect(route('reemplazos.edit', $tramite))
            ->assertSessionHas('upload_error', 'El borrador fue guardado, pero el documento no pudo adjuntarse.');
        $this->assertDatabaseCount('tramites', $before + 1);
        $this->assertDatabaseCount('tramite_adjuntos', 0);
    }

    private function jefe(): User
    {
        return User::query()->where('email', 'jefatura@example.test')->firstOrFail();
    }

    private function unit(): UnidadServicio
    {
        return $this->jefe()->unidadesHabilitadas()->where('activo', true)->firstOrFail();
    }
}
