<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePersonaRequest;
use App\Http\Requests\UpdatePersonaRequest;
use App\Models\Estamento;
use App\Models\Persona;
use App\Models\PersonaUnidadVinculo;
use App\Models\Profesion;
use App\Models\UnidadServicio;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PersonaController extends Controller
{
    public function index(Request $request): View
    {
        $personas = Persona::query()
            ->when($request->filled('q'), fn ($query) => $query->buscar($request->string('q')->toString()))
            ->orderBy('apellido_paterno')->orderBy('nombres')->paginate(15)->withQueryString();

        return view('personas.index', compact('personas'));
    }

    public function create(): View
    {
        return view('personas.create');
    }

    public function store(StorePersonaRequest $request): RedirectResponse
    {
        $persona = Persona::query()->create($request->validated());

        return redirect()->route('personas.show', $persona)->with('status', 'Persona creada.');
    }

    public function show(Persona $persona): View
    {
        $persona->load(['vinculos.unidad', 'vinculos.estamento', 'vinculos.profesion']);

        return view('personas.show', [
            'persona' => $persona,
            'unidades' => UnidadServicio::query()->where('activo', true)->orderBy('nombre')->get(),
            'estamentos' => Estamento::query()->where('activo', true)->orderBy('nombre')->get(),
            'profesiones' => Profesion::query()->where('activo', true)->orderBy('nombre')->get(),
            'estados' => PersonaUnidadVinculo::ESTADOS,
        ]);
    }

    public function edit(Persona $persona): View
    {
        return view('personas.edit', compact('persona'));
    }

    public function update(UpdatePersonaRequest $request, Persona $persona): RedirectResponse
    {
        $persona->update($request->validated());

        return redirect()->route('personas.show', $persona)->with('status', 'Persona actualizada.');
    }

    public function toggleActive(Persona $persona): RedirectResponse
    {
        $persona->update(['active' => ! $persona->active]);

        return back()->with('status', 'Estado de la persona actualizado.');
    }
}
