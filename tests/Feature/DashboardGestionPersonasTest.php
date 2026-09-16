<?php

namespace Tests\Feature;

use App\Enums\AlcanceAccesoOperativo;
use App\Models\EstadoTramite;
use App\Models\Persona;
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

class DashboardGestionPersonasTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private UnidadOrganizacional $unidad;

    private Persona $funcionario;

    private Persona $reemplazante;

    private int $secuencia = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        foreach (['reemplazos.revisar', 'reemplazos.generar_documento', 'tramites.ver_todos'] as $permiso) {
            Permission::findOrCreate($permiso);
        }
        $this->user = User::factory()->create(['active' => true, 'name' => 'Gestora Personas']);
        $this->unidad = UnidadOrganizacional::query()->where('codigo', 'SDGADM-INF')->firstOrFail();
        $this->funcionario = Persona::query()->create(['rut' => '73000101-1', 'nombres' => 'Funcionario Panel', 'active' => true]);
        $this->reemplazante = Persona::query()->create(['rut' => '73000102-2', 'nombres' => 'Reemplazante Panel', 'active' => true]);
    }

    public function test_global_management_dashboard_shows_real_indicators_and_only_five_oldest_actionable_replacements(): void
    {
        $this->user->givePermissionTo(['reemplazos.revisar', 'reemplazos.generar_documento', 'tramites.ver_todos']);
        $estados = ['ENVIADA_GESTION_PERSONAS', 'EN_REVISION', 'LISTA_GENERAR_DOCUMENTO', 'ENVIADA_GESTION_PERSONAS', 'EN_REVISION', 'LISTA_GENERAR_DOCUMENTO'];
        $accionables = collect($estados)->map(fn (string $estado, int $indice) => $this->tramite($estado, $this->unidad, now()->subDays(10 - $indice)));
        $formalizado = $this->tramite('FORMALIZADA', $this->unidad, now()->subDays(20), now()->subDay());
        $documentoGenerado = $this->tramite('DOCUMENTO_GENERADO', $this->unidad, now()->subDays(30));

        $response = $this->actingAs($this->user)->get(route('dashboard'))->assertOk();

        $response
            ->assertSee('Hola, Gestora Personas')
            ->assertSee('Revisa y gestiona los trámites que requieren atención de Gestión de Personas.')
            ->assertSee('Pendientes de revisión')
            ->assertSee('En revisión')
            ->assertSee('Listos para generar documento')
            ->assertSee('Requieren tu atención')
            ->assertSee('Revisar')
            ->assertSee('Continuar revisión')
            ->assertSee('Generar documento')
            ->assertSee('Ver todos los reemplazos')
            ->assertSeeInOrder($accionables->take(5)->pluck('codigo')->all())
            ->assertDontSee($accionables->last()->codigo)
            ->assertDontSee($formalizado->codigo)
            ->assertDontSee($documentoGenerado->codigo)
            ->assertViewHas('panelGestionPersonas', fn (array $panel): bool => $panel['reemplazos']['indicadores']->all() === [
                'pendientes' => 2,
                'en_revision' => 2,
                'para_documento' => 2,
            ] && $panel['reemplazos']['requieren_atencion']->count() === 5);

        foreach (['ENVIADA_GESTION_PERSONAS', 'EN_REVISION', 'LISTA_GENERAR_DOCUMENTO'] as $estado) {
            $response->assertSee(route('gestion-personas.reemplazos.index', ['pestana' => 'activos', 'estado' => $estado]));
        }
    }

    public function test_management_dashboard_respects_operational_unit_scope(): void
    {
        $this->user->givePermissionTo(['reemplazos.revisar', 'reemplazos.generar_documento']);
        UserUnidadAcceso::query()->create([
            'user_id' => $this->user->id,
            'unidad_organizacional_id' => $this->unidad->id,
            'alcance' => AlcanceAccesoOperativo::SOLO_UNIDAD,
            'vigente_desde' => today(),
            'created_by' => $this->user->id,
        ]);
        $otraUnidad = UnidadOrganizacional::query()->whereKeyNot($this->unidad->id)->where('activo', true)->firstOrFail();
        $visible = $this->tramite('ENVIADA_GESTION_PERSONAS', $this->unidad, now()->subDay());
        $fueraDeAlcance = $this->tramite('ENVIADA_GESTION_PERSONAS', $otraUnidad, now()->subDays(2));

        $this->actingAs($this->user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee($visible->codigo)
            ->assertDontSee($fueraDeAlcance->codigo)
            ->assertViewHas('panelGestionPersonas', fn (array $panel): bool => $panel['reemplazos']['indicadores']['pendientes'] === 1
                && $panel['reemplazos']['requieren_atencion']->pluck('id')->all() === [$visible->id]);
    }

    private function tramite(string $estado, UnidadOrganizacional $unidad, \DateTimeInterface $submittedAt, ?\DateTimeInterface $finalizedAt = null): Tramite
    {
        $this->secuencia++;
        $tipo = TipoTramite::query()->where('codigo', 'REEMPLAZO')->firstOrFail();
        $tramite = Tramite::query()->create([
            'public_id' => (string) Str::ulid(),
            'codigo' => 'GP-DASH-'.str_pad((string) $this->secuencia, 3, '0', STR_PAD_LEFT),
            'tipo_tramite_id' => $tipo->id,
            'estado_tramite_id' => EstadoTramite::query()->where('tipo_tramite_id', $tipo->id)->where('codigo', $estado)->firstOrFail()->id,
            'unidad_organizacional_id' => $unidad->id,
            'created_by' => $this->user->id,
            'submitted_at' => $submittedAt,
            'finalized_at' => $finalizedAt,
        ]);
        $tramite->reemplazo()->create([
            'funcionario_id' => $this->funcionario->id,
            'reemplazante_id' => $this->reemplazante->id,
            'tipo_reemplazo_id' => TipoReemplazo::query()->firstOrFail()->id,
            'fecha_funcionario_desde' => '2026-09-01',
            'fecha_funcionario_hasta' => '2026-09-30',
            'fecha_reemplazante_desde' => '2026-09-05',
            'fecha_reemplazante_hasta' => '2026-09-25',
            'justificacion' => 'Justificación dashboard Gestión de Personas',
        ]);

        return $tramite->refresh();
    }
}
