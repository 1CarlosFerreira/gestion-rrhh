<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TipoUnidadOrganizacional;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TipoUnidadOrganizacionalController extends Controller
{
    public function index()
    {
        abort_unless(auth()->user()->can('tipos_unidad_organizacional.gestionar'), 403);

        return view('admin.estructura.tipos', ['tipos' => TipoUnidadOrganizacional::query()->orderBy('orden')->orderBy('nombre')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('tipos_unidad_organizacional.gestionar'), 403);
        TipoUnidadOrganizacional::query()->create($this->validated($request));

        return back()->with('status', 'Tipo creado.');
    }

    public function update(Request $request, TipoUnidadOrganizacional $tipo): RedirectResponse
    {
        abort_unless($request->user()->can('tipos_unidad_organizacional.gestionar'), 403);
        $tipo->update($this->validated($request, $tipo));

        return back()->with('status', 'Tipo actualizado.');
    }

    private function validated(Request $request, ?TipoUnidadOrganizacional $tipo = null): array
    {
        return $request->validate([
            'codigo' => ['required', 'string', 'max:50', Rule::unique('tipos_unidad_organizacional')->ignore($tipo)],
            'nombre' => ['required', 'string', 'max:120'],
            'descripcion' => ['nullable', 'string'],
            'activo' => ['boolean'],
            'orden' => ['required', 'integer', 'min:0', 'max:65535'],
        ]) + ['activo' => false];
    }
}
