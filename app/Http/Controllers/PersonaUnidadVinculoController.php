<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePersonaUnidadVinculoRequest;
use App\Models\Persona;
use App\Models\PersonaUnidadVinculo;
use Illuminate\Http\RedirectResponse;

class PersonaUnidadVinculoController extends Controller
{
    public function store(StorePersonaUnidadVinculoRequest $request, Persona $persona): RedirectResponse
    {
        $persona->vinculos()->create($request->validated());

        return back()->with('status', 'Vínculo agregado.');
    }

    public function update(StorePersonaUnidadVinculoRequest $request, Persona $persona, PersonaUnidadVinculo $vinculo): RedirectResponse
    {
        abort_unless($vinculo->persona_id === $persona->id, 404);
        $vinculo->update($request->validated());

        return back()->with('status', 'Vínculo actualizado.');
    }
}
