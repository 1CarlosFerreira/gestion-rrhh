<?php

namespace Tests\Feature;

use App\Enums\AlcanceAccesoOperativo;
use App\Models\EstadoTramite;
use App\Models\TipoTramite;
use App\Models\Tramite;
use App\Models\UnidadOrganizacional;
use App\Models\User;
use App\Models\UserUnidadAcceso;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Tests\TestCase;

class TramitePolicyAmbitoUnidadTest extends TestCase
{
    use RefreshDatabase;

    private UnidadOrganizacional $unidad;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->unidad = UnidadOrganizacional::query()->where('codigo', 'SDGADM-INF')->firstOrFail();
    }

    public function test_view_uses_current_unit_scope_and_never_creator_ownership(): void
    {
        $creator = User::factory()->create(['active' => true]);
        $creator->givePermissionTo('tramites.ver_unidades');
        $authorized = User::factory()->create(['active' => true]);
        $authorized->givePermissionTo('tramites.ver_unidades');
        $this->access($authorized);
        $tramite = $this->tramite($creator, $this->unidad);

        $this->assertFalse(Gate::forUser($creator)->allows('view', $tramite));
        $this->assertTrue(Gate::forUser($authorized)->allows('view', $tramite));

        $authorized->accesosOperativos()->update(['vigente_hasta' => today()->subDay()]);
        $this->assertFalse(Gate::forUser($authorized->fresh())->allows('view', $tramite));
    }

    public function test_inactive_user_is_denied_even_with_global_permission(): void
    {
        $global = User::factory()->create(['active' => false]);
        $global->givePermissionTo('tramites.ver_todos');
        $tramite = $this->tramite(User::factory()->create(), $this->unidad);

        $this->assertFalse(Gate::forUser($global)->allows('viewAny', Tramite::class));
        $this->assertFalse(Gate::forUser($global)->allows('view', $tramite));
    }

    public function test_global_active_user_keeps_institutional_access_including_tramite_without_unit(): void
    {
        $global = User::factory()->create(['active' => true]);
        $global->givePermissionTo('tramites.ver_todos');
        $tramite = $this->tramite(User::factory()->create(), null);

        $this->assertTrue(Gate::forUser($global)->allows('viewAny', Tramite::class));
        $this->assertTrue(Gate::forUser($global)->allows('view', $tramite));
    }

    public function test_unit_permission_does_not_authorize_tramite_without_unit(): void
    {
        $user = User::factory()->create(['active' => true]);
        $user->givePermissionTo('tramites.ver_unidades');
        $this->access($user);
        $tramite = $this->tramite($user, null);

        $this->assertFalse(Gate::forUser($user)->allows('view', $tramite));
    }

    private function access(User $user): void
    {
        UserUnidadAcceso::query()->create([
            'user_id' => $user->id,
            'unidad_organizacional_id' => $this->unidad->id,
            'alcance' => AlcanceAccesoOperativo::SOLO_UNIDAD,
            'vigente_desde' => today(),
            'created_by' => $user->id,
        ]);
    }

    private function tramite(User $creator, ?UnidadOrganizacional $unidad): Tramite
    {
        $tipo = TipoTramite::query()->where('codigo', 'REEMPLAZO')->firstOrFail();

        return Tramite::query()->create([
            'public_id' => (string) Str::ulid(),
            'codigo' => 'POL-'.Str::random(8),
            'tipo_tramite_id' => $tipo->id,
            'estado_tramite_id' => EstadoTramite::query()->where('tipo_tramite_id', $tipo->id)->where('codigo', 'BORRADOR')->firstOrFail()->id,
            'unidad_organizacional_id' => $unidad?->id,
            'created_by' => $creator->id,
        ]);
    }
}
