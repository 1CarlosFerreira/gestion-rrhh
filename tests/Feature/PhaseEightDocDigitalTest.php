<?php

namespace Tests\Feature;

use App\Actions\DocDigital\RegistrarEnvioDocDigital;
use App\Actions\DocDigital\RegistrarFormalizacionDocDigital;
use App\Actions\HorasExtraordinarias\CargarPlanillaSirh;
use App\Actions\HorasExtraordinarias\CrearHorasExtraordinarias;
use App\Actions\HorasExtraordinarias\FinalizarRevisionHorasExtra;
use App\Actions\HorasExtraordinarias\GenerarInformeTecnicoHorasExtra;
use App\Actions\HorasExtraordinarias\GuardarInformeTecnicoHorasExtra;
use App\Actions\HorasExtraordinarias\RegistrarHorasExtra;
use App\Actions\HorasExtraordinarias\RevisarPlanillaSirh;
use App\Actions\Tramites\Adjuntos\CargarAdjunto;
use App\Actions\Tramites\TransicionarTramite;
use App\Models\DocDigitalRegistro;
use App\Models\EstadoTramite;
use App\Models\Persona;
use App\Models\PersonaUnidadVinculo;
use App\Models\Tramite;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PhaseEightDocDigitalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Storage::fake('private');
    }

    public function test_guest_and_user_without_permission_cannot_register_send(): void
    {
        $document = $this->generatedDocument();
        $payload = ['adjunto_enviado_id' => $document->adjunto_id, 'fecha_envio' => now()->format('Y-m-d H:i:s'), 'registrado_por' => $this->jefe()->id];
        $this->post(route('tramites.docdigital.store', $document->tramite), $payload)->assertRedirect(route('login'));
        $this->actingAs($this->jefe())->post(route('tramites.docdigital.store', $document->tramite), $payload)->assertForbidden();
        $this->assertDatabaseCount('docdigital_registros', 0);
        $this->assertSame('INFORME_TECNICO_GENERADO', $document->tramite->fresh()->estadoTramite->codigo);
    }

    public function test_user_with_docdigital_permission_but_without_tramite_access_cannot_operate_by_url(): void
    {
        $document = $this->generatedDocument();
        $intruder = User::factory()->create();
        $intruder->givePermissionTo('docdigital.registrar_envio');
        $this->expectException(AuthorizationException::class);
        app(RegistrarEnvioDocDigital::class)->execute($document->tramite, $document->adjunto, now(), $intruder);
    }

    public function test_send_uses_server_actor_same_tramite_existing_file_and_nullable_identifier(): void
    {
        $document = $this->generatedDocument();
        $other = $this->generatedDocument();
        $actor = $this->gp();
        $response = $this->actingAs($actor)->post(route('tramites.docdigital.store', $document->tramite), [
            'adjunto_enviado_id' => $document->adjunto_id, 'fecha_envio' => '2026-08-26 10:30:00',
            'registrado_por' => $this->jefe()->id, 'identificador_externo' => null,
        ]);
        $response->assertSessionHasNoErrors();
        $record = DocDigitalRegistro::query()->where('tramite_id', $document->tramite_id)->sole();
        $this->assertSame($actor->id, $record->registrado_por);
        $this->assertNull($record->identificador_externo);
        $this->assertSame('2026-08-26 10:30:00', $record->fecha_envio->format('Y-m-d H:i:s'));
        $this->assertSame(1, $record->intento);
        $this->assertTrue($record->is_current);
        $this->assertSame('ENVIADA_DOCDIGITAL', $document->tramite->fresh()->estadoTramite->codigo);
        $this->assertDatabaseHas('tramite_historial', ['tramite_id' => $document->tramite_id, 'action_code' => 'DOCDIGITAL_ENVIO_REGISTRADO']);
        $this->actingAs($actor)->get(route('tramites.show', $document->tramite))->assertOk()->assertSee('Enviado a DocDigital')->assertSee($document->adjunto->original_name);
        $this->actingAs($actor)->post(route('tramites.docdigital.store', $document->tramite), ['adjunto_enviado_id' => $other->adjunto_id, 'fecha_envio' => now()])->assertSessionHasErrors('adjunto_enviado_id');
        $record->adjuntoEnviado->update(['storage_path' => 'missing/file.pdf']);
        $this->expectException(ValidationException::class);
        app(RegistrarEnvioDocDigital::class)->execute($document->tramite->fresh(), $record->adjuntoEnviado, now(), $actor);
    }

    public function test_resend_preserves_attempt_and_marks_only_new_one_current(): void
    {
        $document = $this->generatedDocument();
        $action = app(RegistrarEnvioDocDigital::class);
        $first = $action->execute($document->tramite, $document->adjunto, now(), $this->gp());
        $second = $action->execute($document->tramite->fresh(), $document->adjunto, now()->addMinute(), $this->admin(), 'DD-2');
        $this->assertSame(2, $second->intento);
        $this->assertTrue($second->is_current);
        $this->assertFalse($first->fresh()->is_current);
        $this->assertDatabaseCount('docdigital_registros', 2);
        $this->assertDatabaseHas('docdigital_registros', ['id' => $first->id, 'intento' => 1]);
        $this->assertDatabaseHas('tramite_historial', ['tramite_id' => $document->tramite_id, 'action_code' => 'DOCDIGITAL_REENVIO_REGISTRADO']);
        $this->assertSame($this->admin()->id, $second->registrado_por);
    }

    public function test_formalization_adds_private_final_document_preserves_sent_document_and_finalizes(): void
    {
        $document = $this->generatedDocument();
        $sent = app(RegistrarEnvioDocDigital::class)->execute($document->tramite, $document->adjunto, now(), $this->gp(), 'DD-1');
        $finalBytes = "%PDF-1.4\nDocumento final ficticio\n%%EOF";
        $record = app(RegistrarFormalizacionDocDigital::class)->execute($document->tramite->fresh(), UploadedFile::fake()->createWithContent('formalizado.pdf', $finalBytes), now(), $this->admin(), 'DD-2', 'Formalización ficticia.');
        $this->assertSame('FORMALIZADO', $record->estado);
        $this->assertSame($this->admin()->id, $record->formalizado_por);
        $this->assertSame($sent->adjunto_enviado_id, $record->adjunto_enviado_id);
        $this->assertNotSame($record->adjunto_enviado_id, $record->adjunto_final_id);
        $this->assertSame('DOCUMENTO_FINAL_DOCDIGITAL', $record->adjuntoFinal->tipoDocumento->codigo);
        Storage::disk('private')->assertExists($record->adjuntoFinal->storage_path);
        $this->assertSame(hash('sha256', $finalBytes), $record->adjuntoFinal->sha256);
        $this->assertSame('FORMALIZADA', $document->tramite->fresh()->estadoTramite->codigo);
        $this->assertNotNull($document->tramite->fresh()->finalized_at);
        $this->assertDatabaseHas('tramite_historial', ['tramite_id' => $document->tramite_id, 'action_code' => 'DOCDIGITAL_FORMALIZACION_REGISTRADA']);
        $this->actingAs($this->admin())->get(route('tramites.show', $document->tramite))->assertOk()->assertSee('Formalizado')->assertSee('formalizado.pdf')->assertSee('DOCDIGITAL_FORMALIZACION_REGISTRADA');
        $this->assertDatabaseCount('persona_unidad_vinculos', PersonaUnidadVinculo::query()->count());
        foreach (['firma', 'signature', 'certificado'] as $column) {
            $this->assertFalse(Schema::hasColumn('docdigital_registros', $column));
        }
    }

    public function test_formalization_requires_permission_and_cannot_impose_actor(): void
    {
        $document = $this->generatedDocument();
        app(RegistrarEnvioDocDigital::class)->execute($document->tramite, $document->adjunto, now(), $this->gp());
        $payload = ['fecha_formalizacion' => now(), 'formalizado_por' => $this->jefe()->id, 'archivo_final' => UploadedFile::fake()->createWithContent('final.pdf', "%PDF-1.4\n%%EOF")];
        $this->actingAs($this->jefe())->post(route('tramites.docdigital.formalize', $document->tramite), $payload)->assertForbidden();
        $this->actingAs($this->admin())->post(route('tramites.docdigital.formalize', $document->tramite), $payload)->assertSessionHasNoErrors();
        $this->assertSame($this->admin()->id, DocDigitalRegistro::query()->where('tramite_id', $document->tramite_id)->sole()->formalizado_por);
    }

    public function test_replacement_without_generated_document_cannot_send(): void
    {
        $tramite = Tramite::query()->whereHas('tipoTramite', fn ($query) => $query->where('codigo', 'REEMPLAZO'))->firstOrFail();
        $state = EstadoTramite::query()->where('tipo_tramite_id', $tramite->tipo_tramite_id)->where('codigo', 'LISTA_GENERAR_DOCUMENTO')->firstOrFail();
        $tramite->update(['estado_tramite_id' => $state->id]);
        $adjunto = app(CargarAdjunto::class)->execute($tramite, UploadedFile::fake()->createWithContent('respaldo.pdf', "%PDF-1.4\nficticio\n%%EOF"), $this->gp());
        $this->expectException(ValidationException::class);
        app(RegistrarEnvioDocDigital::class)->execute($tramite->fresh(), $adjunto, now(), $this->gp());
    }

    private function generatedDocument()
    {
        $persona = Persona::query()->firstOrFail();
        $tramite = app(CrearHorasExtraordinarias::class)->execute($this->jefe()->unidadesHabilitadas()->firstOrFail(), 2026, 8, [$persona->id], $this->jefe())->load('horasExtra.funcionarios');
        $tramite = app(TransicionarTramite::class)->execute($tramite, 'ENVIAR_A_GESTION_PERSONAS', $this->jefe())->load('horasExtra.funcionarios');
        $participant = $tramite->horasExtra->funcionarios->first();
        app(CargarPlanillaSirh::class)->execute($participant, UploadedFile::fake()->createWithContent('planilla.pdf', "%PDF-1.4\nficticio\n%%EOF"), $this->gp());
        app(RegistrarHorasExtra::class)->execute($participant, 1, 0, 1, 0, $this->gp());
        $tramite = app(TransicionarTramite::class)->execute($tramite, 'PUBLICAR_PLANILLAS', $this->gp());
        $tramite = app(TransicionarTramite::class)->execute($tramite, 'INICIAR_REVISION_JEFATURA', $this->jefe())->load('horasExtra.funcionarios.planillaVigente');
        app(RevisarPlanillaSirh::class)->execute($tramite->horasExtra->funcionarios->first(), 'CONFORME', null, $this->jefe());
        $tramite = app(FinalizarRevisionHorasExtra::class)->execute($tramite, $this->jefe());
        app(GuardarInformeTecnicoHorasExtra::class)->execute($tramite, ['horario_diurno' => true, 'horario_festivo' => false, 'retribucion_tiempo' => true, 'retribucion_dinero' => false, 'justificacion_tecnica' => 'Justificación técnica ficticia.', 'medidas_control' => 'Control ficticio.'], $this->jefe());

        return app(GenerarInformeTecnicoHorasExtra::class)->execute($tramite, $this->jefe())->load(['tramite', 'adjunto']);
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
