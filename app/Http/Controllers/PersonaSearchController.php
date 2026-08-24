<?php

namespace App\Http\Controllers;

use App\Models\Persona;
use App\Support\Rut\Rut;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PersonaSearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate(['q' => ['required', 'string', 'min:2', 'max:120']]);

        $personas = Persona::query()->where('active', true)->buscar($validated['q'])
            ->with(['vinculosOperativos.unidad', 'vinculosOperativos.estamento', 'vinculosOperativos.profesion'])
            ->orderBy('apellido_paterno')->limit(20)->get();

        return response()->json($personas->map(fn (Persona $persona): array => [
            'id' => $persona->id,
            'rut' => Rut::format($persona->rut),
            'nombre_completo' => $persona->nombre_completo,
            'vinculos_activos' => $persona->vinculosOperativos->map(fn ($vinculo): array => [
                'unidad' => $vinculo->unidad->nombre,
                'estamento' => $vinculo->estamento?->nombre,
                'profesion' => $vinculo->profesion?->nombre,
                'cargo' => $vinculo->cargo_texto,
                'estado' => $vinculo->status,
            ])->values(),
        ]));
    }
}
