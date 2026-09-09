<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SavePersonaRequest;
use App\Models\Persona;
use App\Models\PersonaUnidadVinculo;
use App\Models\UnidadOrganizacional;
use App\Services\Accesos\AccesoOperativoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PersonaController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('personas.ver');

        $query = Persona::query();

        if ($request->filled('buscar')) {
            $query->buscar($request->string('buscar')->toString());
        }

        $personas = $query
            ->orderBy('apellido_paterno')
            ->orderBy('nombres')
            ->paginate(25)
            ->withQueryString();

        return view('admin.personas.index', compact('personas'));
    }

    public function create(): View
    {
        Gate::authorize('personas.gestionar');

        return view('admin.personas.create');
    }

    public function store(SavePersonaRequest $request): RedirectResponse
    {
        Gate::authorize('personas.gestionar');

        $persona = Persona::create($request->validated());

        return redirect()->route('admin.personas.show', $persona)->with('status', 'Persona creada correctamente.');
    }

    public function show(Request $request, Persona $persona, AccesoOperativoService $accesos): View
    {
        Gate::authorize('personas.ver');

        $verDotacion = $request->user()->active && $request->user()->can('dotacion.ver');
        $vinculos = collect();
        $puedeAgregarVinculo = false;

        if ($verDotacion) {
            $unidades = $request->user()->hasRole('Administrador')
                ? UnidadOrganizacional::query()->get()
                : $accesos->unidadesAccesibles($request->user(), today());

            $vinculos = $persona->vinculosDotacion()
                ->with(['unidad', 'estamento', 'profesion', 'calidadContractual'])
                ->whereIn('unidad_organizacional_id', $unidades->pluck('id'))
                ->orderByDesc('vigente_desde')
                ->get();

            $puedeAgregarVinculo = $persona->active
                && $request->user()->can('dotacion.gestionar')
                && $unidades->contains(fn (UnidadOrganizacional $unidad): bool => $unidad->activo
                    && Gate::allows('create', [PersonaUnidadVinculo::class, $unidad]));
        }

        return view('admin.personas.show', compact('persona', 'verDotacion', 'vinculos', 'puedeAgregarVinculo'));
    }

    public function edit(Persona $persona): View
    {
        Gate::authorize('personas.gestionar');

        return view('admin.personas.edit', compact('persona'));
    }

    public function update(SavePersonaRequest $request, Persona $persona): RedirectResponse
    {
        Gate::authorize('personas.gestionar');

        $persona->update($request->safe()->except('active'));

        return redirect()->route('admin.personas.show', $persona)->with('status', 'Persona actualizada correctamente.');
    }

    public function toggleActive(Persona $persona): RedirectResponse
    {
        Gate::authorize('personas.gestionar');

        if ($persona->active && $persona->vinculosDotacion()->vigentesEn(today())->exists()) {
            throw ValidationException::withMessages([
                'active' => 'No es posible inactivar a la persona mientras mantenga vínculos laborales vigentes. Cierre primero los vínculos vigentes.',
            ]);
        }

        $persona->update(['active' => ! $persona->active]);

        return back()->with('status', $persona->active ? 'Persona reactivada correctamente.' : 'Persona inactivada correctamente.');
    }
}
