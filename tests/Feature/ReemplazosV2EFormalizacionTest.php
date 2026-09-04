<?php

namespace Tests\Feature;

use App\Enums\AlcanceAccesoOperativo;
use App\Models\CalidadContractual;
use App\Models\DocumentoGenerado;
use App\Models\DocumentoPlantilla;
use App\Models\EstadoTramite;
use App\Models\Estamento;
use App\Models\Persona;
use App\Models\PersonaUnidadVinculo;
use App\Models\Profesion;
use App\Models\TipoDocumento;
use App\Models\TipoReemplazo;
use App\Models\TipoTramite;
use App\Models\Tramite;
use App\Models\UnidadOrganizacional;
use App\Models\User;
use App\Models\UserUnidadAcceso;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReemplazosV2EFormalizacionTest extends TestCase
{
    use RefreshDatabase;

    private User $actor;

    private UnidadOrganizacional $unidad;

    private Estamento $estamento;

    private CalidadContractual $calidad;

    private int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Storage::fake('private');
        $this->actor = User::factory()->create(['active' => true]);
        $this->actor->givePermissionTo('reemplazos.formalizar');
        $this->unidad = UnidadOrganizacional::query()->where('codigo', 'SDGADM-INF')->firstOrFail();
        $this->estamento = Estamento::query()->firstOrFail();
        $this->calidad = CalidadContractual::query()->create(['codigo' => 'V2E_TEST', 'nombre' => 'Calidad V2E', 'activo' => true, 'orden' => 1]);
        UserUnidadAcceso::query()->create([
            'user_id' => $this->actor->id,
            'unidad_organizacional_id' => $this->unidad->id,
            'alcance' => AlcanceAccesoOperativo::SOLO_UNIDAD,
            'vigente_desde' => today(),
            'created_by' => $this->actor->id,
        ]);
    }

    public function test_formalization_captures_own_labor_data_reuses_grade_and_creates_exact_dotacion_link(): void
    {
        $tramite = $this->tramiteConDocumento();
        $otroEstamento = Estamento::query()->whereKeyNot($this->estamento->id)->firstOrFail();
        $otraCalidad = CalidadContractual::query()->create(['codigo' => 'V2E_OTRA', 'nombre' => 'Otra calidad', 'activo' => true, 'orden' => 2]);
        $profesion = Profesion::query()->create(['estamento_id' => $otroEstamento->id, 'codigo' => 'PROF_V2E', 'nombre' => 'Profesión V2E', 'activo' => true]);
        $this->crearVinculoFuncionario($tramite, $this->estamento, $this->calidad);

        $response = $this->actingAs($this->actor)->post(route('reemplazos.formalizaciones.store', $tramite), [
            'estamento_id' => $otroEstamento->id,
            'profesion_id' => $profesion->id,
            'calidad_contractual_id' => $otraCalidad->id,
            'cargo_funcion' => '  Enfermera   supervisora  ',
            'identificador_externo' => '  REF-2026-41 ',
            'observacion' => '  Registro administrativo. ',
        ]);

        $response->assertRedirect(route('gestion-personas.reemplazos.show', $tramite));
        $formalizacion = $tramite->formalizacionReemplazo()->sole();
        $vinculo = $tramite->vinculoDotacion()->sole();
        $this->assertSame($tramite->documentosGenerados()->sole()->id, $formalizacion->documento_generado_id);
        $this->assertSame($otroEstamento->id, $formalizacion->estamento_id);
        $this->assertSame($otraCalidad->id, $formalizacion->calidad_contractual_id);
        $this->assertSame($profesion->id, $formalizacion->profesion_id);
        $this->assertSame('Enfermera supervisora', $formalizacion->cargo_funcion);
        $this->assertSame('enfermera supervisora', $formalizacion->cargo_funcion_normalizado);
        $this->assertSame(15, $formalizacion->grado_eus);
        $this->assertSame('REF-2026-41', $formalizacion->identificador_externo);
        $this->assertNull($formalizacion->adjunto_id);
        $this->assertSame($tramite->reemplazo->reemplazante_id, $vinculo->persona_id);
        $this->assertSame($tramite->unidad_organizacional_id, $vinculo->unidad_organizacional_id);
        $this->assertSame($formalizacion->estamento_id, $vinculo->estamento_id);
        $this->assertSame($formalizacion->profesion_id, $vinculo->profesion_id);
        $this->assertSame($formalizacion->calidad_contractual_id, $vinculo->calidad_contractual_id);
        $this->assertSame($formalizacion->cargo_funcion, $vinculo->cargo_funcion);
        $this->assertSame($formalizacion->cargo_funcion_normalizado, $vinculo->cargo_funcion_normalizado);
        $this->assertSame(15, $vinculo->grado_eus);
        $this->assertSame('2026-09-05', $vinculo->vigente_desde->toDateString());
        $this->assertSame('2026-09-25', $vinculo->vigente_hasta->toDateString());
        $this->assertSame('DOCUMENTO_FIRMADO', $vinculo->origen->value);
        $this->assertSame($tramite->id, $vinculo->origen_tramite_id);
        $this->assertNotSame($this->estamento->id, $vinculo->estamento_id);
        $this->assertNotSame($this->calidad->id, $vinculo->calidad_contractual_id);
        $this->assertSame('FORMALIZADA', $tramite->fresh()->estadoTramite->codigo);
        $this->assertNotNull($tramite->fresh()->finalized_at);
        $this->assertDatabaseHas('tramite_historial', ['tramite_id' => $tramite->id, 'action_code' => 'FORMALIZAR_REEMPLAZO', 'user_id' => $this->actor->id]);
    }

    public function test_required_fields_are_validated_and_profession_and_final_document_are_optional(): void
    {
        $tramite = $this->tramiteConDocumento();
        $this->actingAs($this->actor)->post(route('reemplazos.formalizaciones.store', $tramite), [])->assertSessionHasErrors(['estamento_id', 'calidad_contractual_id', 'cargo_funcion']);
        $this->assertSame('DOCUMENTO_GENERADO', $tramite->fresh()->estadoTramite->codigo);

        $this->actingAs($this->actor)->post(route('reemplazos.formalizaciones.store', $tramite), $this->datosValidos())->assertRedirect();
        $this->assertNull($tramite->formalizacionReemplazo()->sole()->profesion_id);
        $this->assertNull($tramite->formalizacionReemplazo()->sole()->adjunto_id);
    }

    public function test_empty_required_catalog_blocks_cleanly_and_ui_explains_the_configuration_needed(): void
    {
        $tramite = $this->tramiteConDocumento();
        $this->calidad->delete();

        $this->actingAs($this->actor)->get(route('gestion-personas.reemplazos.show', $tramite))
            ->assertOk()
            ->assertSee('No existen calidades contractuales activas configuradas');
        $this->actingAs($this->actor)->post(route('reemplazos.formalizaciones.store', $tramite), $this->datosValidos())
            ->assertSessionHasErrors('catalogos');
        $this->assertDatabaseCount('reemplazo_formalizaciones', 0);
        $this->assertNull($tramite->vinculoDotacion);
    }

    public function test_permission_and_operational_access_are_both_required(): void
    {
        $tramite = $this->tramiteConDocumento();
        $sinPermiso = User::factory()->create(['active' => true]);
        UserUnidadAcceso::query()->create(['user_id' => $sinPermiso->id, 'unidad_organizacional_id' => $this->unidad->id, 'alcance' => AlcanceAccesoOperativo::SOLO_UNIDAD, 'vigente_desde' => today(), 'created_by' => $this->actor->id]);
        $this->actingAs($sinPermiso)->post(route('reemplazos.formalizaciones.store', $tramite), $this->datosValidos())->assertForbidden();
        $sinAcceso = User::factory()->create(['active' => true]);
        $sinAcceso->givePermissionTo('reemplazos.formalizar');
        $this->actingAs($sinAcceso)->post(route('reemplazos.formalizaciones.store', $tramite), $this->datosValidos())->assertForbidden();
        $this->assertDatabaseCount('reemplazo_formalizaciones', 0);
    }

    public function test_formalization_is_idempotent_per_tramite(): void
    {
        $tramite = $this->tramiteConDocumento();
        $this->actingAs($this->actor)->post(route('reemplazos.formalizaciones.store', $tramite), $this->datosValidos())->assertRedirect();
        $this->actingAs($this->actor)->post(route('reemplazos.formalizaciones.store', $tramite), $this->datosValidos())->assertRedirect();
        $this->assertDatabaseCount('reemplazo_formalizaciones', 1);
        $this->assertDatabaseCount('persona_unidad_vinculos', 1);
        $this->assertSame(1, $tramite->historial()->where('action_code', 'FORMALIZAR_REEMPLAZO')->count());
    }

    public function test_failure_creating_dotacion_rolls_back_formalization_state_and_history(): void
    {
        $tramite = $this->tramiteConDocumento();
        PersonaUnidadVinculo::query()->create([
            'persona_id' => $tramite->reemplazo->reemplazante_id,
            'unidad_organizacional_id' => $this->unidad->id,
            'estamento_id' => $this->estamento->id,
            'calidad_contractual_id' => $this->calidad->id,
            'cargo_funcion' => 'Cargo V2E',
            'cargo_funcion_normalizado' => 'cargo v2e',
            'vigente_desde' => '2026-09-01',
            'vigente_hasta' => '2026-09-30',
            'origen' => 'MANUAL',
            'created_by' => $this->actor->id,
        ]);

        $this->actingAs($this->actor)->post(route('reemplazos.formalizaciones.store', $tramite), $this->datosValidos())->assertSessionHasErrors('vigente_desde');
        $this->assertDatabaseCount('reemplazo_formalizaciones', 0);
        $this->assertSame('DOCUMENTO_GENERADO', $tramite->fresh()->estadoTramite->codigo);
        $this->assertNull($tramite->fresh()->finalized_at);
        $this->assertSame(0, $tramite->historial()->where('action_code', 'FORMALIZAR_REEMPLAZO')->count());
        $this->assertSame(0, PersonaUnidadVinculo::query()->where('origen_tramite_id', $tramite->id)->count());
    }

    public function test_optional_final_document_uses_private_transversal_storage(): void
    {
        $tramite = $this->tramiteConDocumento();
        $datos = [...$this->datosValidos(), 'documento_final' => UploadedFile::fake()->create('respaldo-final.pdf', 120, 'application/pdf')];
        $this->actingAs($this->actor)->post(route('reemplazos.formalizaciones.store', $tramite), $datos)->assertRedirect();
        $adjunto = $tramite->formalizacionReemplazo()->sole()->adjunto;
        $this->assertNotNull($adjunto);
        $this->assertSame($tramite->reemplazo->reemplazante_id, $adjunto->persona_id);
        $this->assertSame('application/pdf', $adjunto->mime_type);
        Storage::disk('private')->assertExists($adjunto->storage_path);
    }

    public function test_formalization_requires_document_generated_state_and_a_current_generated_document(): void
    {
        $tramite = $this->tramiteConDocumento('LISTA_GENERAR_DOCUMENTO', false);
        $this->actingAs($this->actor)->post(route('reemplazos.formalizaciones.store', $tramite), $this->datosValidos())->assertSessionHasErrors('tramite');
        $tramite->estado_tramite_id = EstadoTramite::query()->where('tipo_tramite_id', $tramite->tipo_tramite_id)->where('codigo', 'DOCUMENTO_GENERADO')->value('id');
        $tramite->save();
        $this->actingAs($this->actor)->post(route('reemplazos.formalizaciones.store', $tramite), $this->datosValidos())->assertSessionHasErrors('documento');
        $this->assertDatabaseCount('reemplazo_formalizaciones', 0);
    }

    private function datosValidos(): array
    {
        return ['estamento_id' => $this->estamento->id, 'calidad_contractual_id' => $this->calidad->id, 'cargo_funcion' => 'Cargo V2E'];
    }

    private function tramiteConDocumento(string $estado = 'DOCUMENTO_GENERADO', bool $conDocumento = true): Tramite
    {
        $this->sequence++;
        $funcionario = Persona::query()->create(['rut' => '73'.sprintf('%05d', $this->sequence).'1-1', 'nombres' => 'Funcionario V2E', 'active' => true]);
        $reemplazante = Persona::query()->create(['rut' => '73'.sprintf('%05d', $this->sequence).'2-2', 'nombres' => 'Reemplazante V2E', 'active' => true]);
        $tipo = TipoTramite::query()->where('codigo', 'REEMPLAZO')->firstOrFail();
        $estadoModel = EstadoTramite::query()->where('tipo_tramite_id', $tipo->id)->where('codigo', $estado)->firstOrFail();
        $tramite = Tramite::query()->create(['public_id' => (string) Str::ulid(), 'codigo' => 'TR-V2E-'.Str::random(6), 'tipo_tramite_id' => $tipo->id, 'estado_tramite_id' => $estadoModel->id, 'unidad_organizacional_id' => $this->unidad->id, 'created_by' => $this->actor->id]);
        $tramite->reemplazo()->create(['funcionario_id' => $funcionario->id, 'reemplazante_id' => $reemplazante->id, 'tipo_reemplazo_id' => TipoReemplazo::query()->firstOrFail()->id, 'fecha_funcionario_desde' => '2026-09-01', 'fecha_funcionario_hasta' => '2026-09-30', 'fecha_reemplazante_desde' => '2026-09-05', 'fecha_reemplazante_hasta' => '2026-09-25', 'justificacion' => 'Continuidad V2E.']);
        $tramite->revisionReemplazo()->create(['grado_eus' => 15, 'cumple_normativa' => true, 'revisado_por' => $this->actor->id, 'revisado_at' => now()]);

        if ($conDocumento) {
            $path = 'tramites/'.$tramite->public_id.'/solicitud.pdf';
            Storage::disk('private')->put($path, '%PDF V2E');
            $tipoDocumento = TipoDocumento::query()->where('codigo', 'DOCUMENTO_GENERADO')->firstOrFail();
            $adjunto = $tramite->adjuntos()->create(['tipo_documento_id' => $tipoDocumento->id, 'uploaded_by' => $this->actor->id, 'original_name' => 'solicitud.pdf', 'stored_name' => 'solicitud.pdf', 'storage_path' => $path, 'mime_type' => 'application/pdf', 'size_bytes' => 9, 'sha256' => hash('sha256', '%PDF V2E'), 'version' => 1, 'status' => 'ACTIVO']);
            DocumentoGenerado::query()->create(['tramite_id' => $tramite->id, 'documento_plantilla_id' => DocumentoPlantilla::query()->where('codigo', 'REEMPLAZO_SOLICITUD_PDF')->firstOrFail()->id, 'tipo_documento_id' => $tipoDocumento->id, 'adjunto_id' => $adjunto->id, 'version' => 1, 'generated_by' => $this->actor->id, 'generated_at' => now(), 'status' => 'VIGENTE']);
        }

        return $tramite->fresh(['reemplazo', 'revisionReemplazo']);
    }

    private function crearVinculoFuncionario(Tramite $tramite, Estamento $estamento, CalidadContractual $calidad): void
    {
        PersonaUnidadVinculo::query()->create(['persona_id' => $tramite->reemplazo->funcionario_id, 'unidad_organizacional_id' => $this->unidad->id, 'estamento_id' => $estamento->id, 'calidad_contractual_id' => $calidad->id, 'cargo_funcion' => 'Cargo funcionario', 'cargo_funcion_normalizado' => 'cargo funcionario', 'vigente_desde' => '2026-01-01', 'origen' => 'MANUAL', 'created_by' => $this->actor->id]);
    }
}
