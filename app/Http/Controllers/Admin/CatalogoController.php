<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClasificacionArea;
use App\Models\Estamento;
use App\Models\Profesion;
use App\Models\TipoReemplazo;
use App\Models\TipoTramite;
use App\Models\UnidadServicio;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CatalogoController extends Controller
{
    private const CATALOGOS = [
        'unidades' => UnidadServicio::class,
        'estamentos' => Estamento::class,
        'profesiones' => Profesion::class,
        'tipos-tramite' => TipoTramite::class,
        'tipos-reemplazo' => TipoReemplazo::class,
        'clasificaciones-area' => ClasificacionArea::class,
    ];

    public function index(): View
    {
        $catalogos = collect(self::CATALOGOS)->mapWithKeys(
            fn (string $model, string $key) => [$key => $model::query()->orderBy('nombre')->get()],
        );

        return view('admin.catalogos.index', compact('catalogos'));
    }

    public function toggleActive(string $catalogo, int $id): RedirectResponse
    {
        abort_unless(array_key_exists($catalogo, self::CATALOGOS), 404);
        /** @var Model $registro */
        $registro = self::CATALOGOS[$catalogo]::query()->findOrFail($id);
        $registro->update(['activo' => ! $registro->activo]);

        return back()->with('status', 'Estado del catálogo actualizado.');
    }
}
