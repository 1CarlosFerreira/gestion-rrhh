<?php

namespace Tests\Feature;

use App\Enums\AlcanceAccesoOperativo;
use App\Models\EstadoTramite;
use App\Models\Persona;
use App\Models\TipoDocumento;
use App\Models\TipoReemplazo;
use App\Models\TipoTramite;
use App\Models\Tramite;
use App\Models\UnidadOrganizacional;
use App\Models\User;
use App\Models\UserUnidadAcceso;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class DashboardMisTramitesTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private UnidadOrganizacional $unidad;

    private Persona $funcionario;

    private Persona $reemplazante;

    private int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Permission::findOrCreate('tramites.ver_propios');
        Permission::findOrCreate('reemplazos.crear');
        $this->user = User::factory()->create(['active' => true]);
        $this->user->givePermissionTo(['tramites.ver_propios', 'reemplazos.crear']);
        $this->unidad = UnidadOrganizacional::query()->where('codigo', 'SDGADM-INF')->firstOrFail();
        UserUnidadAcceso::query()->create(['user_id' => $this->user->id, 'unidad_organizacional_id' => $this->unidad->id, 'alcance' => AlcanceAccesoOperativo::SOLO_UNIDAD, 'vigente_desde' => today(), 'created_by' => $this->user->id]);
        $this->funcionario = Persona::query()->create(['rut' => '72000101-1', 'nombres' => 'Funcionario Dashboard', 'active' => true]);
        $this->reemplazante = Persona::query()->create(['rut' => '72000102-2', 'nombres' => 'Reemplazante Dashboard', 'active' => true]);
    }

    public function test_dashboard_lists_only_five_most_recent_own_open_procedures(): void
    {
        $ownOpen = collect(range(1, 6))->map(fn (int $index) => $this->tramite('ENVIADA_GESTION_PERSONAS', $this->user, now()->subDays(7 - $index)));
        $formalizada = $this->tramite('FORMALIZADA', $this->user, now(), now());
        $other = User::factory()->create(['active' => true]);
        $other->givePermissionTo('tramites.ver_propios');
        $ajeno = $this->tramite('ENVIADA_GESTION_PERSONAS', $other, now()->addMinute());

        $response = $this->actingAs($this->user)->get(route('dashboard'))->assertOk();

        $expected = $ownOpen->reverse()->take(5)->pluck('codigo')->all();
        $response->assertSeeInOrder($expected);
        $response->assertDontSee($ownOpen->first()->codigo)
            ->assertDontSee($formalizada->codigo)
            ->assertDontSee($ajeno->codigo);
    }

    public function test_dashboard_uses_continue_for_editable_states_and_view_for_other_open_states(): void
    {
        $borrador = $this->tramite('BORRADOR', $this->user, now()->subMinutes(4));
        $devuelta = $this->tramite('DEVUELTA_PARA_CORRECCION', $this->user, now()->subMinutes(3));
        $enviada = $this->tramite('ENVIADA_GESTION_PERSONAS', $this->user, now()->subMinutes(2));
        $revision = $this->tramite('EN_REVISION', $this->user, now()->subMinute());
        $documento = $this->tramite('DOCUMENTO_GENERADO', $this->user, now());

        $response = $this->actingAs($this->user)->get(route('dashboard'))->assertOk();
        foreach ([$borrador, $devuelta] as $tramite) {
            $response->assertSee(route('reemplazos.edit', $tramite));
        }
        foreach ([$enviada, $revision, $documento] as $tramite) {
            $response->assertSee(route('reemplazos.show', $tramite));
        }
        $response->assertSee('Gestiona y realiza seguimiento a tus solicitudes.')
            ->assertSee('+ Nueva solicitud')
            ->assertSee('Requieren atención')
            ->assertSee('En tramitación')
            ->assertSee('Abiertos')
            ->assertSee('Requieren mi atención')
            ->assertSee('Continuar')
            ->assertSee('Ver')
            ->assertDontSee('se encuentra en construcción');
    }

    public function test_read_only_detail_uses_tramite_view_policy_and_has_no_operational_controls(): void
    {
        $tramite = $this->tramite('ENVIADA_GESTION_PERSONAS', $this->user, now());
        $tipoDocumento = TipoDocumento::query()->firstOrFail();
        $tramite->adjuntos()->create(['tipo_documento_id' => $tipoDocumento->id, 'uploaded_by' => $this->user->id, 'original_name' => 'respaldo.pdf', 'stored_name' => 'archivo.pdf', 'storage_path' => 'tramites/prueba/archivo.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 10, 'sha256' => hash('sha256', 'prueba'), 'version' => 1, 'status' => 'ACTIVO']);

        $this->actingAs($this->user)->get(route('reemplazos.show', $tramite))
            ->assertOk()
            ->assertSee($tramite->codigo)
            ->assertSee($this->unidad->nombre)
            ->assertSee('Funcionario Dashboard')
            ->assertSee('Reemplazante Dashboard')
            ->assertSee('Justificación de prueba')
            ->assertSee('respaldo.pdf')
            ->assertSee('Volver al Inicio')
            ->assertSee('Estado del trámite')
            ->assertSee('Lista para generar documento')
            ->assertSee('21 de 30')
            ->assertSee('9 días sin cobertura')
            ->assertSee('70% cubierto')
            ->assertSee('Período solicitado')
            ->assertSee('Ver calendario')
            ->assertSee('Cubierto')
            ->assertSee('Sin cobertura')
            ->assertSee('Fuera del período')
            ->assertSee('versión 1')
            ->assertDontSee('Guardar borrador')
            ->assertDontSee('Enviar a Gestión de Personas')
            ->assertDontSee('Iniciar revisión');

        $unauthorized = User::factory()->create(['active' => true]);
        $unauthorized->givePermissionTo('tramites.ver_propios');
        $this->actingAs($unauthorized)->get(route('reemplazos.show', $tramite))->assertForbidden();
    }

    public function test_attention_section_is_hidden_when_there_are_no_editable_open_procedures(): void
    {
        $this->tramite('ENVIADA_GESTION_PERSONAS', $this->user, now());

        $this->actingAs($this->user)->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Requieren mi atención');
    }

    private function tramite(string $estado, User $creator, \DateTimeInterface $updatedAt, ?\DateTimeInterface $finalizedAt = null): Tramite
    {
        $this->sequence++;
        $tipo = TipoTramite::query()->where('codigo', 'REEMPLAZO')->firstOrFail();
        $tramite = Tramite::query()->create([
            'public_id' => (string) Str::ulid(),
            'codigo' => 'DASH-'.str_pad((string) $this->sequence, 3, '0', STR_PAD_LEFT),
            'tipo_tramite_id' => $tipo->id,
            'estado_tramite_id' => EstadoTramite::query()->where('tipo_tramite_id', $tipo->id)->where('codigo', $estado)->firstOrFail()->id,
            'unidad_organizacional_id' => $this->unidad->id,
            'created_by' => $creator->id,
            'finalized_at' => $finalizedAt,
        ]);
        $tramite->reemplazo()->create(['funcionario_id' => $this->funcionario->id, 'reemplazante_id' => $this->reemplazante->id, 'tipo_reemplazo_id' => TipoReemplazo::query()->firstOrFail()->id, 'fecha_funcionario_desde' => '2026-09-01', 'fecha_funcionario_hasta' => '2026-09-30', 'fecha_reemplazante_desde' => '2026-09-05', 'fecha_reemplazante_hasta' => '2026-09-25', 'justificacion' => 'Justificación de prueba']);
        $tramite->forceFill(['updated_at' => $updatedAt])->saveQuietly();

        return $tramite->refresh();
    }
}
