<?php

namespace App\Http\Controllers\Admin;

use App\Enums\EstadoVinculoDotacion;
use App\Enums\OrigenVinculoDotacion;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SavePersonaUnidadVinculoRequest;
use App\Models\CalidadContractual;
use App\Models\Estamento;
use App\Models\Persona;
use App\Models\PersonaUnidadVinculo;
use App\Models\Profesion;
use App\Models\UnidadOrganizacional;
use App\Services\Accesos\AccesoOperativoService;
use App\Services\Dotacion\DotacionService;
use App\Services\EstructuraOrganizacionalService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DotacionController extends Controller
{
    public function index(Request $request, AccesoOperativoService $accesos, EstructuraOrganizacionalService $estructura): View
    {
        Gate::authorize('viewAny', PersonaUnidadVinculo::class);
        $fecha = $request->date('fecha')?->toDateString() ?? today()->toDateString();
        $permitidas = $request->user()->hasRole('Administrador') ? UnidadOrganizacional::query()->pluck('id') : $accesos->unidadesAccesibles($request->user(), $fecha)->pluck('id');
        $unidadId = $request->integer('unidad_id');
        $ids = $permitidas;
        if ($unidadId) {
            abort_unless($permitidas->contains($unidadId), 403);
            $ids = collect([$unidadId]);
            if ($request->boolean('incluir_descendientes')) {
                $unidad = UnidadOrganizacional::query()->findOrFail($unidadId);
                $ids = $ids->merge($estructura->descendientes($unidad)->pluck('id'))->intersect($permitidas);
            }
        }
        $estado = $request->has('estado') ? $request->string('estado')->toString() : EstadoVinculoDotacion::VIGENTE->value;
        $query = PersonaUnidadVinculo::query()->with(['persona.user', 'unidad', 'estamento', 'profesion', 'calidadContractual', 'creadoPor'])->whereIn('unidad_organizacional_id', $ids)
            ->when($request->filled('persona'), fn (Builder $q) => $q->whereHas('persona', fn (Builder $p) => $p->buscar($request->string('persona'))))
            ->when($request->integer('estamento_id'), fn (Builder $q, int $id) => $q->where('estamento_id', $id))
            ->when($request->integer('profesion_id'), fn (Builder $q, int $id) => $q->where('profesion_id', $id))
            ->when($request->integer('calidad_contractual_id'), fn (Builder $q, int $id) => $q->where('calidad_contractual_id', $id));
        match ($estado) {
            EstadoVinculoDotacion::FUTURO->value => $query->whereDate('vigente_desde', '>', $fecha),
            EstadoVinculoDotacion::FINALIZADO->value => $query->whereNotNull('vigente_hasta')->whereDate('vigente_hasta', '<', $fecha),
            EstadoVinculoDotacion::VIGENTE->value => $query->vigentesEn($fecha),
            default => null,
        };
        $vinculos = $query->orderByDesc('vigente_desde')->get();
        $unidades = UnidadOrganizacional::query()->whereIn('id', $permitidas)->orderBy('nombre')->get();
        $unidadesAgrupadas = UnidadOrganizacional::query()->with('tipo')->whereIn('id', $ids)->get()
            ->each(function (UnidadOrganizacional $unidad) use ($estructura): void {
                $ancestros = $estructura->ancestros($unidad);
                $unidad->setAttribute('ruta_jerarquica', $ancestros->pluck('nombre')->implode(' › '));
                $unidad->setAttribute('es_encabezado_jerarquico', $unidad->parent_id === null || $unidad->tipo?->codigo === 'SUBDIRECCION');
                $unidad->setAttribute('orden_jerarquico', $ancestros->push($unidad)->map(
                    fn (UnidadOrganizacional $nodo): string => sprintf('%05d:%s', $nodo->orden, mb_strtolower($nodo->nombre)),
                )->implode('/'));
            })
            ->sortBy('orden_jerarquico', SORT_NATURAL)
            ->values();

        return view('admin.dotacion.index', ['vinculos' => $vinculos, 'fecha' => $fecha, 'estado' => $estado, 'estados' => EstadoVinculoDotacion::cases(), 'unidades' => $unidades, 'unidadesAgrupadas' => $unidadesAgrupadas, 'estamentos' => Estamento::query()->orderBy('nombre')->get(), 'profesiones' => Profesion::query()->orderBy('nombre')->get(), 'calidades' => CalidadContractual::query()->orderBy('orden')->get()]);
    }

    public function create(Request $request, AccesoOperativoService $accesos, EstructuraOrganizacionalService $estructura): View
    {
        abort_unless($request->user()->can('dotacion.gestionar'), 403);
        abort_unless($request->user()->hasRole('Administrador') || $accesos->unidadesAccesibles($request->user(), today())->isNotEmpty(), 403);

        $request->validate(['persona_id' => ['nullable', 'integer', Rule::exists('personas', 'id')->where('active', true)]]);

        return $this->form(null, $request, $accesos, $estructura)
            ->with('personaSeleccionadaId', $request->integer('persona_id') ?: null);
    }

    public function store(SavePersonaUnidadVinculoRequest $request, DotacionService $service): RedirectResponse
    {
        $unidad = UnidadOrganizacional::query()->findOrFail($request->integer('unidad_organizacional_id'));
        Gate::authorize('create', [PersonaUnidadVinculo::class, $unidad]);
        $service->crear($request->validated(), $request->user());

        return redirect()->route('admin.dotacion.index')->with('status', 'Vínculo laboral registrado.');
    }

    public function edit(Request $request, PersonaUnidadVinculo $vinculo, AccesoOperativoService $accesos, EstructuraOrganizacionalService $estructura): View
    {
        Gate::authorize('update', $vinculo);

        return $this->form($vinculo, $request, $accesos, $estructura);
    }

    public function update(SavePersonaUnidadVinculoRequest $request, PersonaUnidadVinculo $vinculo, DotacionService $service): RedirectResponse
    {
        Gate::authorize('update', $vinculo);
        $service->actualizar($vinculo, $request->validated(), $request->user());

        return redirect()->route('admin.dotacion.index')->with('status', 'Vínculo actualizado.');
    }

    public function close(Request $request, PersonaUnidadVinculo $vinculo, DotacionService $service): RedirectResponse
    {
        Gate::authorize('update', $vinculo);
        $data = $request->validate(['vigente_hasta' => ['required', 'date', 'after_or_equal:'.$vinculo->vigente_desde->toDateString()]]);
        $service->cerrar($vinculo, $data['vigente_hasta'], $request->user());

        return back()->with('status', 'Vínculo cerrado.');
    }

    public function persona(Request $request, Persona $persona, AccesoOperativoService $accesos, EstructuraOrganizacionalService $estructura): View
    {
        $permitidas = $request->user()->hasRole('Administrador') ? UnidadOrganizacional::query()->pluck('id') : $accesos->unidadesAccesibles($request->user(), today())->pluck('id');
        abort_unless($request->user()->can('dotacion.ver') && $persona->vinculosDotacion()->whereIn('unidad_organizacional_id', $permitidas)->exists(), 403);
        $persona->load(['user.accesosOperativos.unidad', 'responsabilidades' => fn ($q) => $q->with('unidad')->whereIn('unidad_organizacional_id', $permitidas), 'vinculosDotacion' => fn ($q) => $q->with(['unidad', 'estamento', 'profesion', 'calidadContractual'])->whereIn('unidad_organizacional_id', $permitidas)->orderByDesc('vigente_desde')]);

        return view('admin.dotacion.persona', compact('persona', 'estructura'));
    }

    private function form(?PersonaUnidadVinculo $vinculo, Request $request, AccesoOperativoService $accesos, EstructuraOrganizacionalService $estructura): View
    {
        $unidades = $request->user()->hasRole('Administrador') ? UnidadOrganizacional::query()->activas()->get() : $accesos->unidadesAccesibles($request->user(), today());

        return view('admin.dotacion.form', ['vinculo' => $vinculo, 'personas' => Persona::query()->where('active', true)->orderBy('apellido_paterno')->get(), 'unidades' => $unidades->map(fn ($u) => ['id' => $u->id, 'ruta' => $estructura->ruta($u)]), 'estamentos' => Estamento::query()->where('activo', true)->orderBy('nombre')->get(), 'profesiones' => Profesion::query()->where('activo', true)->orderBy('nombre')->get(), 'calidades' => CalidadContractual::query()->where('activo', true)->orderBy('orden')->get(), 'origenes' => OrigenVinculoDotacion::cases()]);
    }
}
