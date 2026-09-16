<?php

namespace Tests\Feature;

use App\Enums\AlcanceAccesoOperativo;
use App\Models\CalidadContractual;
use App\Models\ClasificacionArea;
use App\Models\DocumentoGenerado;
use App\Models\DocumentoPlantilla;
use App\Models\EstadoTramite;
use App\Models\Estamento;
use App\Models\Persona;
use App\Models\PersonaUnidadVinculo;
use App\Models\TipoReemplazo;
use App\Models\TipoTramite;
use App\Models\Tramite;
use App\Models\UnidadOrganizacional;
use App\Models\User;
use App\Models\UserUnidadAcceso;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ReemplazosV2DDocumentoTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private UnidadOrganizacional $unidad;

    private int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Storage::fake('private');
        Permission::findOrCreate('reemplazos.generar_documento');
        $this->user = User::factory()->create(['active' => true]);
        $this->user->givePermissionTo(['reemplazos.generar_documento', 'reemplazos.revisar']);
        $this->unidad = UnidadOrganizacional::query()->where('codigo', 'SDGADM-INF')->firstOrFail();
        UserUnidadAcceso::query()->create(['user_id' => $this->user->id, 'unidad_organizacional_id' => $this->unidad->id, 'alcance' => AlcanceAccesoOperativo::SOLO_UNIDAD, 'vigente_desde' => today(), 'created_by' => $this->user->id]);
    }

    public function test_authorized_generation_creates_private_pdf_hash_snapshot_transition_and_history(): void
    {
        $tramite = $this->tramiteListo();
        $this->actingAs($this->user)->post(route('reemplazos.documentos.store', $tramite))->assertRedirect();
        $documento = DocumentoGenerado::query()->with('adjunto')->sole();
        Storage::disk('private')->assertExists($documento->adjunto->storage_path);
        $bytes = Storage::disk('private')->get($documento->adjunto->storage_path);
        $this->assertStringStartsWith('%PDF', $bytes);
        $this->assertSame(hash('sha256', $bytes), $documento->adjunto->sha256);
        $this->assertStringNotContainsString('public', $documento->adjunto->storage_path);
        $this->assertSame(1, $documento->version);
        $this->assertSame('DOCUMENTO_GENERADO', $tramite->fresh()->estadoTramite->codigo);
        $this->assertDatabaseHas('tramite_historial', ['tramite_id' => $tramite->id, 'user_id' => $this->user->id, 'action_code' => 'GENERAR_DOCUMENTO']);
        $this->assertSame('Funcionario V2D', $documento->metadata['funcionario']['nombre']);
        $this->assertSame('Reemplazante V2D', $documento->metadata['reemplazante']['nombre']);
        $this->assertSame(['desde' => '2026-09-01', 'hasta' => '2026-09-30'], collect($documento->metadata['funcionario'])->only(['desde', 'hasta'])->all());
        $this->assertSame(['desde' => '2026-09-05', 'hasta' => '2026-09-25'], collect($documento->metadata['reemplazante'])->only(['desde', 'hasta'])->all());
        $this->assertSame(9, $documento->metadata['solicitud']['dias_sin_cobertura']);
        $this->assertSame(15, $documento->metadata['revision']['grado_eus']);
        $this->assertSame('Área V2D', $documento->metadata['revision']['clasificacion_area']);
        $this->assertSame($this->unidad->nombre, $documento->metadata['tramite']['unidad']);
        $this->assertFalse(Schema::hasTable('docdigital_registros'));
        $this->assertNull($tramite->vinculoDotacion);
        $this->assertSame(1, $tramite->reemplazo()->count());
    }

    public function test_scoped_generation_requires_permission_and_operational_access(): void
    {
        $tramite = $this->tramiteListo();
        $withoutPermission = User::factory()->create(['active' => true]);
        UserUnidadAcceso::query()->create(['user_id' => $withoutPermission->id, 'unidad_organizacional_id' => $this->unidad->id, 'alcance' => AlcanceAccesoOperativo::SOLO_UNIDAD, 'vigente_desde' => today(), 'created_by' => $this->user->id]);
        $this->actingAs($withoutPermission)->post(route('reemplazos.documentos.store', $tramite))->assertForbidden();
        $withoutAccess = User::factory()->create(['active' => true]);
        $withoutAccess->givePermissionTo('reemplazos.generar_documento');
        $this->actingAs($withoutAccess)->post(route('reemplazos.documentos.store', $tramite))->assertForbidden();
        $inactiveGlobal = User::factory()->create(['active' => false]);
        $inactiveGlobal->givePermissionTo(['reemplazos.generar_documento', 'tramites.ver_todos']);
        $this->actingAs($inactiveGlobal)->post(route('reemplazos.documentos.store', $tramite))->assertForbidden();
        $this->assertSame(0, DocumentoGenerado::query()->count());
    }

    public function test_global_generator_can_generate_and_download_without_operational_access(): void
    {
        $tramite = $this->tramiteListo();
        $global = User::factory()->create(['active' => true]);
        $global->givePermissionTo(['reemplazos.generar_documento', 'tramites.ver_todos']);

        $this->assertCount(0, $global->accesosOperativos);
        $this->actingAs($global)->post(route('reemplazos.documentos.store', $tramite))
            ->assertRedirect(route('gestion-personas.reemplazos.show', $tramite));

        $documento = DocumentoGenerado::query()->sole();
        $this->assertSame('DOCUMENTO_GENERADO', $tramite->fresh()->estadoTramite->codigo);
        $this->actingAs($global)->get(route('reemplazos.documentos.download', [$tramite, $documento]))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_document_screen_renders_summary_and_contextual_document_action(): void
    {
        $tramite = $this->tramiteListo();

        $this->actingAs($this->user)->get(route('gestion-personas.reemplazos.show', $tramite))
            ->assertOk()
            ->assertSee('Volver a Reemplazos')
            ->assertSee($tramite->codigo)
            ->assertSee($this->unidad->nombre)
            ->assertSee('Funcionario V2D')
            ->assertSee('Cargo V2D')
            ->assertSee(Estamento::query()->firstOrFail()->nombre)
            ->assertSee('Reemplazante V2D')
            ->assertSee('Días totales')
            ->assertSee('Días cubiertos')
            ->assertSee('Días sin cobertura')
            ->assertSee('Área V2D')
            ->assertSee('Resumen para generación')
            ->assertSee('Ver antecedentes completos')
            ->assertSee('Ver revisión de Gestión de Personas')
            ->assertSee('El documento se generará con los antecedentes aprobados')
            ->assertSee('Generar documento')
            ->assertDontSee('Descargar PDF');

        $this->actingAs($this->user)->post(route('reemplazos.documentos.store', $tramite))->assertRedirect();
        $documento = DocumentoGenerado::query()->sole();

        $this->actingAs($this->user)->get(route('gestion-personas.reemplazos.show', $tramite))
            ->assertOk()
            ->assertSee($documento->adjunto->original_name)
            ->assertSee('Versión 1')
            ->assertSee($this->user->name)
            ->assertSee('Descargar PDF')
            ->assertDontSee('Resumen para generación')
            ->assertDontSee('Ver antecedentes completos')
            ->assertSee('Ver antecedentes del trámite y cobertura')
            ->assertSee('Ver revisión de Gestión de Personas');
    }

    public function test_generation_is_rejected_from_every_previous_state(): void
    {
        foreach (['BORRADOR', 'ENVIADA_GESTION_PERSONAS', 'EN_REVISION', 'DEVUELTA_PARA_CORRECCION'] as $codigo) {
            $tramite = $this->tramiteListo($codigo);
            $this->actingAs($this->user)->post(route('reemplazos.documentos.store', $tramite))->assertSessionHasErrors('tramite');
            $this->assertSame($codigo, $tramite->fresh()->estadoTramite->codigo);
        }
        $this->assertSame(0, DocumentoGenerado::query()->count());
    }

    public function test_render_failure_leaves_state_and_database_unchanged(): void
    {
        $tramite = $this->tramiteListo();
        DocumentoPlantilla::query()->where('codigo', 'REEMPLAZO_SOLICITUD_PDF')->update(['template_path' => 'pdf.no-existe']);
        $this->actingAs($this->user)->post(route('reemplazos.documentos.store', $tramite))->assertSessionHasErrors('documento');
        $this->assertSame('LISTA_GENERAR_DOCUMENTO', $tramite->fresh()->estadoTramite->codigo);
        $this->assertSame(0, DocumentoGenerado::query()->count());
        $this->assertSame([], Storage::disk('private')->allFiles());
    }

    public function test_download_requires_permission_scope_and_matching_document(): void
    {
        $tramite = $this->tramiteListo();
        $this->actingAs($this->user)->post(route('reemplazos.documentos.store', $tramite));
        $documento = DocumentoGenerado::query()->sole();
        $this->actingAs($this->user)->get(route('reemplazos.documentos.download', [$tramite, $documento]))->assertOk()->assertHeader('content-type', 'application/pdf');
        $outsider = User::factory()->create(['active' => true]);
        $outsider->givePermissionTo('reemplazos.generar_documento');
        $this->actingAs($outsider)->get(route('reemplazos.documentos.download', [$tramite, $documento]))->assertForbidden();
        $withoutPermission = User::factory()->create(['active' => true]);
        UserUnidadAcceso::query()->create(['user_id' => $withoutPermission->id, 'unidad_organizacional_id' => $this->unidad->id, 'alcance' => AlcanceAccesoOperativo::SOLO_UNIDAD, 'vigente_desde' => today(), 'created_by' => $this->user->id]);
        $this->actingAs($withoutPermission)->get(route('reemplazos.documentos.download', [$tramite, $documento]))->assertForbidden();
    }

    private function tramiteListo(string $estado = 'LISTA_GENERAR_DOCUMENTO'): Tramite
    {
        $this->sequence++;
        $funcionario = Persona::query()->create(['rut' => '72'.sprintf('%05d', $this->sequence).'1-1', 'nombres' => 'Funcionario V2D', 'active' => true]);
        $reemplazante = Persona::query()->create(['rut' => '72'.sprintf('%05d', $this->sequence).'2-2', 'nombres' => 'Reemplazante V2D', 'active' => true]);
        $calidad = CalidadContractual::query()->firstOrCreate(['codigo' => 'V2D_TEST'], ['nombre' => 'Prueba V2D', 'activo' => true]);
        PersonaUnidadVinculo::query()->create(['persona_id' => $funcionario->id, 'unidad_organizacional_id' => $this->unidad->id, 'estamento_id' => Estamento::query()->firstOrFail()->id, 'calidad_contractual_id' => $calidad->id, 'cargo_funcion' => 'Cargo V2D', 'cargo_funcion_normalizado' => 'cargo v2d', 'vigente_desde' => '2026-01-01', 'origen' => 'MANUAL', 'created_by' => $this->user->id]);
        $tipo = TipoTramite::query()->where('codigo', 'REEMPLAZO')->firstOrFail();
        $estadoModel = EstadoTramite::query()->where('tipo_tramite_id', $tipo->id)->where('codigo', $estado)->firstOrFail();
        $tramite = Tramite::query()->create(['public_id' => (string) Str::ulid(), 'codigo' => 'TR-V2D-'.Str::random(6), 'tipo_tramite_id' => $tipo->id, 'estado_tramite_id' => $estadoModel->id, 'unidad_organizacional_id' => $this->unidad->id, 'created_by' => $this->user->id]);
        $tramite->reemplazo()->create(['funcionario_id' => $funcionario->id, 'reemplazante_id' => $reemplazante->id, 'tipo_reemplazo_id' => TipoReemplazo::query()->firstOrFail()->id, 'fecha_funcionario_desde' => '2026-09-01', 'fecha_funcionario_hasta' => '2026-09-30', 'fecha_reemplazante_desde' => '2026-09-05', 'fecha_reemplazante_hasta' => '2026-09-25', 'justificacion' => 'Continuidad del servicio V2D.']);
        $clasificacion = ClasificacionArea::query()->firstOrCreate(['codigo' => 'AREA_V2D'], ['nombre' => 'Área V2D', 'activo' => true]);
        $tramite->revisionReemplazo()->create(['grado_eus' => 15, 'clasificacion_area_id' => $clasificacion->id, 'cumple_normativa' => true, 'revisado_por' => $this->user->id, 'revisado_at' => now()]);

        return $tramite;
    }
}
