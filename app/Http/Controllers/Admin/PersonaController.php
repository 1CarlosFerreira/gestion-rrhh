<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SavePersonaRequest;
use App\Models\Persona;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class PersonaController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('personas.ver');

        $query = Persona::query();

        if ($request->filled('buscar')) {
            $query->buscar($request->string('buscar'));
        }

        $personas = $query->orderBy('apellido_paterno')->paginate(20);

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

    public function show(Persona $persona): View
    {
        Gate::authorize('personas.ver');

        $persona->load(['user', 'vinculosDotacion.unidad', 'vinculosDotacion.estamento', 'vinculosDotacion.profesion', 'vinculosDotacion.calidadContractual']);

        return view('admin.personas.show', compact('persona'));
    }

    public function edit(Persona $persona): View
    {
        Gate::authorize('personas.gestionar');

        return view('admin.personas.edit', compact('persona'));
    }

    public function update(SavePersonaRequest $request, Persona $persona): RedirectResponse
    {
        Gate::authorize('personas.gestionar');

        $persona->update($request->validated());

        return redirect()->route('admin.personas.show', $persona)->with('status', 'Persona actualizada correctamente.');
    }
}