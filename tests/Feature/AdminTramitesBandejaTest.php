<?php

namespace Tests\Feature;

use App\Models\EstadoTramite;
use App\Models\Persona;
use App\Models\TipoReemplazo;
use App\Models\TipoTramite;
use App\Models\Tramite;
use App\Models\TramiteReemplazo;
use App\Models\UnidadOrganizacional;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminTramitesBandejaTest extends TestCase
{
    use RefreshDatabase;

    private User $administrador;

    private TipoTramite $tipo;

    private EstadoTramite $estado;

    private UnidadOrganizacional $unidad;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->administrador = User::factory()->create(['name' => 'Administradora Global']);
        $this->administrador->givePermissionTo('tramites.ver_todos');
        $this->tipo = TipoTramite::query()->where('codigo', 'REEMPLAZO')->firstOrFail();
        $this->estado = EstadoTramite::query()
            ->whereBelongsTo($this->tipo, 'tipoTramite')
            ->where('codigo', 'BORRADOR')
            ->firstOrFail();
        $this->unidad = UnidadOrganizacional::query()->firstOrFail();
    }

    public function test_usuario_con_visibilidad_global_puede_acceder_y_otro_usuario_no(): void
    {
        $this->actingAs($this->administrador)
            ->get(route('admin.tramites.index'))
            ->assertOk()
            ->assertSeeText('Trámites')
            ->assertSeeText('Todos los trámites');

        $sinPermiso = User::factory()->create();

        $this->actingAs($sinPermiso)
            ->get(route('admin.tramites.index'))
            ->assertForbidden();

        $this->actingAs($sinPermiso)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSeeText('Todos los trámites');
    }

    public function test_muestra_globalmente_tramites_de_distintas_unidades(): void
    {
        $otraUnidad = UnidadOrganizacional::query()->whereKeyNot($this->unidad->id)->firstOrFail();
        $primero = $this->crearTramite(['codigo' => 'TR-2026-000001']);
        $segundo = $this->crearTramite(['codigo' => 'TR-2026-000002', 'unidad_organizacional_id' => $otraUnidad->id]);

        $response = $this->actingAs($this->administrador)->get(route('admin.tramites.index'));

        $response->assertOk()->assertSeeText($primero->codigo)->assertSeeText($segundo->codigo);
        $this->assertSame(2, $response->viewData('tramites')->total());
    }

    public function test_busqueda_encuentra_codigo_por_prefijo_y_personas_del_reemplazo(): void
    {
        $tramite = $this->crearTramite(['codigo' => 'TR-2026-004321'], true, 'María Buscada', 'Pedro Reemplazante');
        $this->crearTramite(['codigo' => 'TR-2026-009999'], true, 'Persona Distinta', 'Otra Persona');

        $this->actingAs($this->administrador)
            ->get(route('admin.tramites.index', ['buscar' => 'TR-2026-004']))
            ->assertOk()
            ->assertSeeText($tramite->codigo)
            ->assertDontSeeText('TR-2026-009999');

        $this->actingAs($this->administrador)
            ->get(route('admin.tramites.index', ['buscar' => 'Buscada']))
            ->assertOk()
            ->assertSeeText($tramite->codigo)
            ->assertSeeText('María Buscada');
    }

    public function test_filtra_por_tipo_y_estado_y_rechaza_un_estado_de_otro_tipo(): void
    {
        $reemplazo = $this->crearTramite(['codigo' => 'TR-2026-000010']);
        $otroTipo = TipoTramite::query()->create(['codigo' => 'OTRO', 'nombre' => 'Otro proceso', 'activo' => true]);
        $otroEstado = EstadoTramite::query()->create([
            'tipo_tramite_id' => $otroTipo->id,
            'codigo' => 'INICIAL',
            'nombre' => 'Inicial',
            'orden' => 1,
            'activo' => true,
        ]);
        $otro = $this->crearTramite([
            'codigo' => 'TR-2026-000011',
            'tipo_tramite_id' => $otroTipo->id,
            'estado_tramite_id' => $otroEstado->id,
        ]);

        $this->actingAs($this->administrador)
            ->get(route('admin.tramites.index', ['tipo' => $this->tipo->id, 'estado' => $this->estado->id]))
            ->assertOk()
            ->assertSeeText($reemplazo->codigo)
            ->assertDontSeeText($otro->codigo);

        $this->actingAs($this->administrador)
            ->get(route('admin.tramites.index', ['tipo' => $this->tipo->id, 'estado' => $otroEstado->id]))
            ->assertSessionHasErrors('estado');
    }

    public function test_filtra_por_unidad_y_creador(): void
    {
        $otroCreador = User::factory()->create(['name' => 'Creador Filtrado']);
        $otraUnidad = UnidadOrganizacional::query()->whereKeyNot($this->unidad->id)->firstOrFail();
        $coincide = $this->crearTramite([
            'codigo' => 'TR-2026-000020',
            'unidad_organizacional_id' => $otraUnidad->id,
            'created_by' => $otroCreador->id,
        ]);
        $this->crearTramite(['codigo' => 'TR-2026-000021']);

        $this->actingAs($this->administrador)
            ->get(route('admin.tramites.index', ['unidad' => $otraUnidad->id, 'creador' => $otroCreador->id]))
            ->assertOk()
            ->assertSeeText($coincide->codigo)
            ->assertDontSeeText('TR-2026-000021')
            ->assertSeeText('Más filtros (2)')
            ->assertSee('x-data="{ filtrosAbiertos: true }"', false);
    }

    public function test_filtros_secundarios_inician_cerrados_si_no_hay_ninguno_activo(): void
    {
        $this->actingAs($this->administrador)
            ->get(route('admin.tramites.index'))
            ->assertOk()
            ->assertSeeText('Más filtros')
            ->assertSee('x-data="{ filtrosAbiertos: false }"', false)
            ->assertSee('aria-controls="filtros-secundarios-tramites"', false)
            ->assertSee('x-bind:aria-expanded="filtrosAbiertos.toString()"', false);
    }

    public function test_filtra_por_rango_de_fechas_inclusivo(): void
    {
        $dentro = $this->crearTramite(['codigo' => 'TR-2026-000030']);
        Tramite::query()->whereKey($dentro)->update(['created_at' => '2026-09-15 12:00:00', 'updated_at' => '2026-09-15 12:00:00']);
        $fuera = $this->crearTramite(['codigo' => 'TR-2026-000031']);
        Tramite::query()->whereKey($fuera)->update(['created_at' => '2026-09-20 12:00:00', 'updated_at' => '2026-09-20 12:00:00']);

        $this->actingAs($this->administrador)
            ->get(route('admin.tramites.index', ['fecha_desde' => '2026-09-15', 'fecha_hasta' => '2026-09-15']))
            ->assertOk()
            ->assertSeeText($dentro->codigo)
            ->assertDontSeeText($fuera->codigo);
    }

    public function test_ordena_por_fecha_e_id_en_ambos_sentidos(): void
    {
        $antiguo = $this->crearTramite(['codigo' => 'TR-2026-000040']);
        Tramite::query()->whereKey($antiguo)->update(['created_at' => '2026-09-01 10:00:00']);
        $reciente = $this->crearTramite(['codigo' => 'TR-2026-000041']);
        Tramite::query()->whereKey($reciente)->update(['created_at' => '2026-09-02 10:00:00']);

        $this->actingAs($this->administrador)
            ->get(route('admin.tramites.index'))
            ->assertSeeTextInOrder([$reciente->codigo, $antiguo->codigo]);

        $this->actingAs($this->administrador)
            ->get(route('admin.tramites.index', ['orden' => 'antiguos']))
            ->assertSeeTextInOrder([$antiguo->codigo, $reciente->codigo]);
    }

    public function test_admite_20_50_y_100_por_pagina_y_usa_20_ante_un_valor_invalido(): void
    {
        foreach ([20, 50, 100] as $perPage) {
            $response = $this->actingAs($this->administrador)
                ->get(route('admin.tramites.index', ['per_page' => $perPage]));
            $response->assertOk();
            $this->assertSame($perPage, $response->viewData('tramites')->perPage());
        }

        $response = $this->actingAs($this->administrador)
            ->get(route('admin.tramites.index', ['per_page' => 999]));
        $response->assertOk();
        $this->assertSame(20, $response->viewData('tramites')->perPage());
    }

    public function test_paginacion_conserva_todos_los_filtros(): void
    {
        foreach (range(1, 21) as $index) {
            $this->crearTramite(['codigo' => sprintf('TR-2026-%06d', 100000 + $index)]);
        }

        $params = [
            'buscar' => 'TR-2026-',
            'tipo' => $this->tipo->id,
            'estado' => $this->estado->id,
            'unidad' => $this->unidad->id,
            'creador' => $this->administrador->id,
            'fecha_desde' => now()->subDay()->format('Y-m-d'),
            'fecha_hasta' => now()->addDay()->format('Y-m-d'),
            'orden' => 'antiguos',
            'per_page' => 20,
        ];

        $response = $this->actingAs($this->administrador)->get(route('admin.tramites.index', $params));

        $response->assertOk();
        $nextUrl = $response->viewData('tramites')->nextPageUrl();
        $this->assertNotNull($nextUrl);
        foreach ($params as $key => $value) {
            $this->assertStringContainsString(urlencode($key).'='.urlencode((string) $value), $nextUrl);
        }
    }

    public function test_presenta_funcionario_y_reemplazante_sin_acciones_operativas_y_enlaza_al_detalle(): void
    {
        $tramite = $this->crearTramite(
            ['codigo' => 'TR-2026-000050'],
            true,
            'Ana Funcionaria',
            'Luis Reemplazante',
        );

        $this->actingAs($this->administrador)
            ->get(route('admin.tramites.index'))
            ->assertOk()
            ->assertSeeText('Ana Funcionaria')
            ->assertSeeText('Reemplazante: Luis Reemplazante')
            ->assertSee(route('reemplazos.show', $tramite), false)
            ->assertSeeText('Ver')
            ->assertDontSeeText('Aprobar')
            ->assertDontSeeText('Formalizar')
            ->assertDontSeeText('Generar documento');
    }

    public function test_detalle_conserva_el_contexto_de_la_bandeja_y_retorna_a_todos_los_tramites(): void
    {
        $tramite = $this->crearTramite(
            ['codigo' => 'TR-CONTEXTO-000001'],
            true,
            'Ana Contexto',
            'Luis Contexto',
        );

        foreach (range(2, 21) as $index) {
            $this->crearTramite(['codigo' => sprintf('TR-CONTEXTO-%06d', $index)]);
        }

        $filters = [
            'buscar' => 'TR-CONTEXTO-',
            'tipo' => $this->tipo->id,
            'estado' => $this->estado->id,
            'unidad' => $this->unidad->id,
            'creador' => $this->administrador->id,
            'fecha_desde' => now()->subDay()->format('Y-m-d'),
            'fecha_hasta' => now()->addDay()->format('Y-m-d'),
            'orden' => 'recientes',
            'per_page' => 20,
            'page' => 2,
        ];
        $detailUrl = route('reemplazos.show', [
            'tramite' => $tramite,
            'from' => 'admin_tramites',
            'return' => $filters,
        ]);
        $returnUrl = route('admin.tramites.index', $filters);

        $this->actingAs($this->administrador)
            ->get(route('admin.tramites.index', $filters))
            ->assertOk()
            ->assertSee($detailUrl);

        $this->actingAs($this->administrador)
            ->get($detailUrl)
            ->assertOk()
            ->assertSeeText('Volver a Todos los trámites')
            ->assertSee($returnUrl);
    }

    public function test_detalle_directo_usa_inicio_y_no_acepta_destinos_externos(): void
    {
        $tramite = $this->crearTramite(['codigo' => 'TR-2026-000051'], true);

        $this->actingAs($this->administrador)
            ->get(route('reemplazos.show', $tramite))
            ->assertOk()
            ->assertSeeText('Volver al Inicio')
            ->assertSee(route('dashboard'), false);

        $this->actingAs($this->administrador)
            ->get(route('reemplazos.show', [
                'tramite' => $tramite,
                'from' => 'https://evil.example',
                'return' => ['url' => 'https://evil.example/steal'],
            ]))
            ->assertOk()
            ->assertSeeText('Volver al Inicio')
            ->assertSee(route('dashboard'), false)
            ->assertDontSee('https://evil.example/steal', false);
    }

    public function test_tipo_sin_detalle_implementado_se_maneja_de_forma_segura(): void
    {
        $tipo = TipoTramite::query()->create(['codigo' => 'FUTURO', 'nombre' => 'Proceso futuro', 'activo' => true]);
        $estado = EstadoTramite::query()->create([
            'tipo_tramite_id' => $tipo->id,
            'codigo' => 'INICIAL',
            'nombre' => 'Inicial',
            'orden' => 1,
            'activo' => true,
        ]);
        $tramite = $this->crearTramite([
            'codigo' => 'TR-2026-000060',
            'tipo_tramite_id' => $tipo->id,
            'estado_tramite_id' => $estado->id,
        ]);

        $this->actingAs($this->administrador)
            ->get(route('admin.tramites.index'))
            ->assertOk()
            ->assertSeeText($tramite->codigo)
            ->assertSeeText('Asunto no disponible')
            ->assertSeeText('Detalle no disponible');
    }

    private function crearTramite(
        array $overrides = [],
        bool $conReemplazo = false,
        string $funcionarioNombre = 'Funcionaria Ejemplo',
        string $reemplazanteNombre = 'Reemplazante Ejemplo',
    ): Tramite {
        $tramite = Tramite::query()->create(array_merge([
            'public_id' => (string) Str::ulid(),
            'codigo' => 'TR-'.Str::upper(Str::random(12)),
            'tipo_tramite_id' => $this->tipo->id,
            'estado_tramite_id' => $this->estado->id,
            'unidad_organizacional_id' => $this->unidad->id,
            'created_by' => $this->administrador->id,
        ], $overrides));

        if ($conReemplazo) {
            $funcionario = Persona::query()->create([
                'rut' => (string) random_int(10000000, 39999999).'-1',
                'nombres' => $funcionarioNombre,
                'active' => true,
            ]);
            $reemplazante = Persona::query()->create([
                'rut' => (string) random_int(40000000, 69999999).'-2',
                'nombres' => $reemplazanteNombre,
                'active' => true,
            ]);
            TramiteReemplazo::query()->create([
                'tramite_id' => $tramite->id,
                'funcionario_id' => $funcionario->id,
                'reemplazante_id' => $reemplazante->id,
                'tipo_reemplazo_id' => TipoReemplazo::query()->firstOrFail()->id,
                'fecha_funcionario_desde' => '2026-09-01',
                'fecha_funcionario_hasta' => '2026-09-10',
                'fecha_reemplazante_desde' => '2026-09-01',
                'fecha_reemplazante_hasta' => '2026-09-10',
            ]);
        }

        return $tramite;
    }
}
