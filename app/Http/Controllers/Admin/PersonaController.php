<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Personas\ActualizarIdentidadPersona;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SavePersonaRequest;
use App\Models\Persona;
use App\Models\PersonaUnidadVinculo;
use App\Models\UnidadOrganizacional;
use App\Models\User;
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

    public function create(Request $request): View
    {
        Gate::authorize('personas.gestionar');

        return view('admin.personas.create', [
            'puedeContinuarDotacion' => $this->puedeCrearVinculos($request->user()),
        ]);
    }

    public function store(SavePersonaRequest $request): RedirectResponse
    {
        Gate::authorize('personas.gestionar');

        $persona = Persona::create($request->validated());

        if ($request->string('continuar')->toString() === 'dotacion'
            && $persona->active
            && $this->puedeCrearVinculos($request->user())) {
            return redirect()->route('admin.dotacion.create', ['persona_id' => $persona->id]);
        }

        return redirect()->route('admin.personas.show', $persona)->with('status', 'Persona creada correctamente.');
    }

    public function show(Request $request, Persona $persona, AccesoOperativoService $accesos): View
    {
        Gate::authorize('personas.ver');

        $persona->loadMissing('user.roles');
        if ($request->user()->can('accesos_operativos.ver')) {
            $persona->loadMissing('user.accesosOperativos.unidad');
        }
        if ($request->user()->can('responsabilidades.ver')) {
            $persona->loadMissing('responsabilidades.unidad');
        }

        $ambitoOperacion = collect();
        if ($persona->user !== null) {
            $responsabilidadesAmbito = $persona->relationLoaded('responsabilidades')
                ? $persona->responsabilidades->filter(fn ($responsabilidad) => $responsabilidad->puede_aprobar
                    && $responsabilidad->estaVigenteEn(today())
                    && $responsabilidad->unidad->activo)
                : collect();
            $accesosAmbito = $persona->user->relationLoaded('accesosOperativos')
                ? $persona->user->accesosOperativos->filter(fn ($acceso) => $acceso->estaVigenteEn(today()) && $acceso->unidad->activo)
                : collect();

            $ambitoOperacion = $responsabilidadesAmbito->pluck('unidad')
                ->merge($accesosAmbito->pluck('unidad'))
                ->unique('id')
                ->sortBy('nombre')
                ->map(fn (UnidadOrganizacional $unidad) => [
                    'unidad' => $unidad,
                    'responsabilidades' => $responsabilidadesAmbito->where('unidad_organizacional_id', $unidad->id),
                    'accesos' => $accesosAmbito->where('unidad_organizacional_id', $unidad->id),
                ])
                ->values();
        }

        $verDotacion = $request->user()->active && $request->user()->can('dotacion.ver');
        $vinculos = collect();
        $puedeAgregarVinculo = false;

        if ($verDotacion) {
            $unidades = $request->user()->can('dotacion.ver_todas')
                ? UnidadOrganizacional::query()->get()
                : $accesos->unidadesAccesibles($request->user(), today());

            $vinculos = $persona->vinculosDotacion()
                ->with(['unidad', 'estamento', 'profesion', 'calidadContractual', 'tramiteOrigen'])
                ->whereIn('unidad_organizacional_id', $unidades->pluck('id'))
                ->orderByDesc('vigente_desde')
                ->get();

            $puedeAgregarVinculo = $persona->active
                && $request->user()->can('dotacion.gestionar')
                && $unidades->contains(fn (UnidadOrganizacional $unidad): bool => $unidad->activo
                    && Gate::allows('create', [PersonaUnidadVinculo::class, $unidad]));
        }

        return view('admin.personas.show', compact('persona', 'ambitoOperacion', 'verDotacion', 'vinculos', 'puedeAgregarVinculo'));
    }

    public function edit(Persona $persona): View
    {
        Gate::authorize('personas.gestionar');

        return view('admin.personas.edit', compact('persona'));
    }

    public function update(SavePersonaRequest $request, Persona $persona, ActualizarIdentidadPersona $actualizarIdentidad): RedirectResponse
    {
        Gate::authorize('personas.gestionar');

        $actualizarIdentidad->execute($persona, $request->safe()->except('active'));

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

    private function puedeCrearVinculos(User $user): bool
    {
        if (! $user->active || ! $user->hasRole('Administrador') || ! $user->can('dotacion.gestionar')) {
            return false;
        }

        return UnidadOrganizacional::query()->where('activo', true)->get()
            ->contains(fn (UnidadOrganizacional $unidad): bool => Gate::forUser($user)->allows('create', [PersonaUnidadVinculo::class, $unidad]));
    }
}
