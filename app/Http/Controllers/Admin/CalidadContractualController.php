<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveCalidadContractualRequest;
use App\Models\CalidadContractual;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class CalidadContractualController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', CalidadContractual::class);
        $calidades = CalidadContractual::query()->withCount('vinculos')->when($request->filled('buscar'), fn (Builder $q) => $q->where(fn (Builder $q) => $q->where('codigo', 'like', '%'.$request->string('buscar').'%')->orWhere('nombre', 'like', '%'.$request->string('buscar').'%')))->orderBy('orden')->orderBy('nombre')->get();

        return view('admin.calidades.index', compact('calidades'));
    }

    public function store(SaveCalidadContractualRequest $request): RedirectResponse
    {
        Gate::authorize('create', CalidadContractual::class);
        CalidadContractual::query()->create([...$request->validated(), 'activo' => $request->boolean('activo')]);

        return back()->with('status', 'Calidad contractual registrada.');
    }

    public function update(SaveCalidadContractualRequest $request, CalidadContractual $calidad): RedirectResponse
    {
        Gate::authorize('update', $calidad);
        $calidad->update([...$request->validated(), 'activo' => $request->boolean('activo')]);

        return back()->with('status', 'Calidad contractual actualizada.');
    }

    public function toggle(CalidadContractual $calidad): RedirectResponse
    {
        Gate::authorize('update', $calidad);
        $calidad->update(['activo' => ! $calidad->activo]);

        return back()->with('status', 'Estado actualizado.');
    }
}
