<?php

namespace App\Http\Controllers\Admin;

use App\Enums\TipoResponsabilidad;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveUnidadResponsableRequest;
use App\Models\Persona;
use App\Models\UnidadOrganizacional;
use App\Models\UnidadResponsable;
use App\Services\EstructuraOrganizacionalService;
use App\Services\Responsabilidades\ResponsabilidadInstitucionalService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class UnidadResponsableController extends Controller
{
    public function index(Request $request, EstructuraOrganizacionalService $estructura): View
    {
        Gate::authorize('viewAny', UnidadResponsable::class);
        $fecha = $request->date('fecha')?->toDateString() ?? now()->toDateString();
        $query = UnidadResponsable::query()->with(['unidad.parent', 'persona.user', 'creadoPor', 'actualizadoPor'])
            ->when($request->integer('unidad_id'), fn (Builder $q, int $id) => $q->where('unidad_organizacional_id', $id))
            ->when($request->filled('tipo'), fn (Builder $q) => $q->where('tipo', $request->string('tipo')))
            ->when($request->filled('persona'), fn (Builder $q) => $q->whereHas('persona', fn (Builder $p) => $p->buscar($request->string('persona'))));
        if (! $request->boolean('incluir_finalizados')) {
            $query->vigentesEn($fecha);
        }
        $responsabilidades = $query->orderByDesc('vigente_desde')->get();

        return view('admin.responsabilidades.index', ['responsabilidades' => $responsabilidades, 'unidades' => UnidadOrganizacional::query()->orderBy('nombre')->get(), 'tipos' => TipoResponsabilidad::cases(), 'fecha' => $fecha, 'estructura' => $estructura]);
    }

    public function create(EstructuraOrganizacionalService $estructura): View
    {
        Gate::authorize('create', UnidadResponsable::class);

        return $this->form(null, $estructura);
    }

    public function store(SaveUnidadResponsableRequest $request, ResponsabilidadInstitucionalService $service): RedirectResponse
    {
        $service->crear($this->data($request), $request->user());

        return redirect()->route('admin.responsabilidades.index')->with('status', 'Responsabilidad registrada.');
    }

    public function edit(UnidadResponsable $responsabilidad, EstructuraOrganizacionalService $estructura): View
    {
        Gate::authorize('update', $responsabilidad);

        return $this->form($responsabilidad, $estructura);
    }

    public function update(SaveUnidadResponsableRequest $request, UnidadResponsable $responsabilidad, ResponsabilidadInstitucionalService $service): RedirectResponse
    {
        Gate::authorize('update', $responsabilidad);
        $service->actualizar($responsabilidad, $this->data($request), $request->user());

        return redirect()->route('admin.responsabilidades.index')->with('status', 'Responsabilidad actualizada.');
    }

    public function close(Request $request, UnidadResponsable $responsabilidad, ResponsabilidadInstitucionalService $service): RedirectResponse
    {
        Gate::authorize('update', $responsabilidad);
        $validated = $request->validate(['vigente_hasta' => ['required', 'date', 'after_or_equal:'.$responsabilidad->vigente_desde->toDateString()]]);
        $service->cerrar($responsabilidad, $validated['vigente_hasta'], $request->user());

        return back()->with('status', 'Responsabilidad cerrada.');
    }

    private function form(?UnidadResponsable $responsabilidad, EstructuraOrganizacionalService $estructura): View
    {
        return view('admin.responsabilidades.form', ['responsabilidad' => $responsabilidad, 'tipos' => TipoResponsabilidad::cases(), 'personas' => Persona::query()->where('active', true)->orderBy('apellido_paterno')->orderBy('nombres')->get(), 'unidades' => UnidadOrganizacional::query()->where('activo', true)->orderBy('nombre')->get()->map(fn ($u) => ['id' => $u->id, 'ruta' => $estructura->ruta($u)])]);
    }

    private function data(SaveUnidadResponsableRequest $request): array
    {
        return [...$request->validated(), 'puede_aprobar' => $request->boolean('puede_aprobar')];
    }
}
