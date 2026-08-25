<?php

namespace Tests\Feature;

use App\Models\Tramite;
use App\Models\TramiteAdjunto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PhaseFourAttachmentsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private');
        $this->seed();
    }

    public function test_guest_cannot_download_and_user_without_permission_cannot_upload(): void
    {
        $tramite = Tramite::query()->firstOrFail();
        $this->get("/tramites/{$tramite->public_id}/adjuntos/1/descargar")->assertRedirect('/login');
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('tramites.adjuntos.store', $tramite), ['archivo' => $this->pdf()])->assertForbidden();
    }

    public function test_valid_upload_stores_private_record_hash_and_safe_server_fields(): void
    {
        $admin = $this->admin();
        $tramite = $admin->tramitesCreados()->firstOrFail();
        $file = $this->pdf('informe original.pdf');

        $this->actingAs($admin)->post(route('tramites.adjuntos.store', $tramite), ['archivo' => $file, 'uploaded_by' => User::query()->whereKeyNot($admin->id)->value('id')])->assertRedirect();
        $adjunto = TramiteAdjunto::query()->latest('id')->firstOrFail();

        Storage::disk('private')->assertExists($adjunto->storage_path);
        $this->assertNotSame('informe original.pdf', $adjunto->stored_name);
        $this->assertSame(hash('sha256', "%PDF-1.4\ncontenido ficticio"), $adjunto->sha256);
        $this->assertSame($admin->id, $adjunto->uploaded_by);
        $this->assertStringStartsWith('tramites/'.$tramite->public_id.'/', $adjunto->storage_path);
        $this->assertDatabaseHas('tramite_historial', ['tramite_id' => $tramite->id, 'action_code' => 'ADJUNTO_CARGADO']);
    }

    public function test_person_is_optional_and_catalog_is_seeded(): void
    {
        $this->upload();
        $this->assertNull(TramiteAdjunto::query()->latest('id')->value('persona_id'));
        $this->assertDatabaseCount('tipos_documento', 6);
    }

    public function test_size_and_mime_validation_reject_invalid_files(): void
    {
        $tramite = $this->admin()->tramitesCreados()->firstOrFail();
        $this->actingAs($this->admin())->post(route('tramites.adjuntos.store', $tramite), ['archivo' => UploadedFile::fake()->create('grande.pdf', 10241, 'application/pdf')])->assertSessionHasErrors('archivo');
        $this->actingAs($this->admin())->post(route('tramites.adjuntos.store', $tramite), ['archivo' => UploadedFile::fake()->create('malware.exe', 10, 'application/x-msdownload')])->assertSessionHasErrors('archivo');
        $this->assertDatabaseCount('tramite_adjuntos', 0);
    }

    public function test_new_version_creates_record_replaces_previous_and_keeps_both_files(): void
    {
        $first = $this->upload();
        $oldPath = $first->storage_path;
        $this->actingAs($this->admin())->post(route('tramites.adjuntos.version', [$first->tramite, $first]), ['archivo' => $this->pdf('version-2.pdf', 'contenido dos')])->assertRedirect();
        $second = TramiteAdjunto::query()->latest('id')->firstOrFail();

        $this->assertNotSame($first->id, $second->id);
        $this->assertSame(2, $second->version);
        $this->assertSame($first->id, $second->replaces_adjunto_id);
        $this->assertSame('REEMPLAZADO', $first->fresh()->status);
        Storage::disk('private')->assertExists($oldPath);
        Storage::disk('private')->assertExists($second->storage_path);
        $this->assertDatabaseHas('tramite_historial', ['tramite_id' => $first->tramite_id, 'action_code' => 'ADJUNTO_VERSIONADO']);
    }

    public function test_annul_preserves_file_and_metadata_and_records_history(): void
    {
        $adjunto = $this->upload();
        $hash = $adjunto->sha256;
        $this->actingAs($this->admin())->patch(route('tramites.adjuntos.annul', [$adjunto->tramite, $adjunto]))->assertRedirect();

        $this->assertSame('ANULADO', $adjunto->fresh()->status);
        $this->assertSame($hash, $adjunto->fresh()->sha256);
        Storage::disk('private')->assertExists($adjunto->storage_path);
        $this->assertDatabaseHas('tramite_historial', ['tramite_id' => $adjunto->tramite_id, 'action_code' => 'ADJUNTO_ANULADO']);
    }

    public function test_download_returns_file_but_cannot_be_manipulated_across_transactions(): void
    {
        $adjunto = $this->upload();
        $other = Tramite::query()->whereKeyNot($adjunto->tramite_id)->firstOrFail();
        $this->actingAs($this->admin())->get(route('tramites.adjuntos.download', [$adjunto->tramite, $adjunto]))->assertOk()->assertDownload($adjunto->original_name);
        $this->actingAs($this->admin())->get(route('tramites.adjuntos.download', [$other, $adjunto]))->assertForbidden();
    }

    public function test_user_without_transaction_access_cannot_download_even_with_permission(): void
    {
        $adjunto = $this->upload();
        $user = User::factory()->create();
        $user->givePermissionTo('tramites.adjuntos.descargar');
        $this->actingAs($user)->get(route('tramites.adjuntos.download', [$adjunto->tramite, $adjunto]))->assertForbidden();
    }

    public function test_storage_path_is_unique_and_future_phase_tables_do_not_exist(): void
    {
        $this->assertTrue(Schema::hasTable('tramite_adjuntos'));
        foreach (['documentos_generados', 'documento_plantillas', 'docdigital_registros'] as $table) {
            $this->assertFalse(Schema::hasTable($table));
        }
    }

    private function upload(): TramiteAdjunto
    {
        $tramite = $this->admin()->tramitesCreados()->firstOrFail();
        $this->actingAs($this->admin())->post(route('tramites.adjuntos.store', $tramite), ['archivo' => $this->pdf()])->assertRedirect();

        return TramiteAdjunto::query()->latest('id')->firstOrFail();
    }

    private function pdf(string $name = 'respaldo.pdf', string $content = 'contenido ficticio'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n".$content);
    }

    private function admin(): User
    {
        return User::query()->where('email', 'admin@example.test')->firstOrFail();
    }
}
