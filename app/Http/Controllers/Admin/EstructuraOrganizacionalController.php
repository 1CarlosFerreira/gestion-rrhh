<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUnidadOrganizacionalRequest;
use App\Http\Requests\Admin\UpdateUnidadOrganizacionalRequest;
use App\Models\TipoUnidadOrganizacional;
use App\Models\UnidadOrganizacional;
use App\Services\EstructuraOrganizacionalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class EstructuraOrganizacionalController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', UnidadOrganizacional::class);
        $buscar = trim((string) $request->query('buscar'));
        $incluirInactivos = $request->boolean('incluir_inactivos');
        $query = UnidadOrganizacional::query()->with('tipo')->when(! $incluirInactivos, fn ($q) => $q->where('activo', true));

        if ($buscar !== '') {
            $resultados = $query->where(fn ($q) => $q->where('nombre', 'like', "%{$buscar}%")->orWhere('codigo', 'like', "%{$buscar}%")->orWhere('sigla', 'like', "%{$buscar}%"))->orderBy('nombre')->get();
            $raices = collect();
        } else {
            $resultados = collect();
            $raices = $query->raices()->with(['children' => fn ($q) => $q->when(! $incluirInactivos, fn ($q) => $q->where('activo', true))->with('tipo')])->get();
        }

        return view('admin.estructura.index', compact('raices', 'resultados', 'buscar', 'incluirInactivos'));
    }

    public function organigrama(): View
    {
        Gate::authorize('viewAny', UnidadOrganizacional::class);

        $raices = UnidadOrganizacional::query()
            ->where('activo', true)
            ->raices()
            ->with(['activeChildren'])
            ->get();

        return view('admin.estructura.organigrama', compact('raices'));
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', UnidadOrganizacional::class);

        return $this->form(null, $request->integer('parent_id') ?: null);
    }

    public function store(StoreUnidadOrganizacionalRequest $request): RedirectResponse
    {
        UnidadOrganizacional::query()->create($this->data($request->validated()));

        return redirect()->route('admin.estructura.index')->with('status', 'Unidad creada.');
    }

    public function edit(UnidadOrganizacional $unidad): View
    {
        Gate::authorize('update', $unidad);

        return $this->form($unidad, $unidad->parent_id);
    }

    public function update(UpdateUnidadOrganizacionalRequest $request, UnidadOrganizacional $unidad, EstructuraOrganizacionalService $service): RedirectResponse
    {
        $parent = $request->filled('parent_id') ? UnidadOrganizacional::findOrFail($request->integer('parent_id')) : null;
        $service->validarMovimiento($unidad, $parent);
        $unidad->update($this->data($request->validated()));

        return redirect()->route('admin.estructura.index')->with('status', 'Unidad actualizada.');
    }

    public function toggle(UnidadOrganizacional $unidad): RedirectResponse
    {
        Gate::authorize('update', $unidad);
        $unidad->update(['activo' => ! $unidad->activo]);

        return back()->with('status', 'Estado actualizado.');
    }

    private function form(?UnidadOrganizacional $unidad, ?int $parentId): View
    {
        $excluded = $unidad ? app(EstructuraOrganizacionalService::class)->descendientes($unidad)->pluck('id')->push($unidad->id) : collect();

        return view('admin.estructura.form', [
            'unidad' => $unidad, 'parentId' => $parentId,
            'tipos' => TipoUnidadOrganizacional::query()->where('activo', true)->orderBy('orden')->get(),
            'padres' => UnidadOrganizacional::query()->where('activo', true)->whereNotIn('id', $excluded)->orderBy('nombre')->get(),
        ]);
    }

    private function data(array $data): array
    {
        return [...$data, 'activo' => (bool) ($data['activo'] ?? false), 'participa_en_aprobacion' => (bool) ($data['participa_en_aprobacion'] ?? false)];
    }
}
