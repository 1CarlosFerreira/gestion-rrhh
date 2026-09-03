<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AlcanceAccesoOperativo;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveUserUnidadAccesoRequest;
use App\Models\UnidadOrganizacional;
use App\Models\User;
use App\Models\UserUnidadAcceso;
use App\Services\Accesos\AccesoOperativoService;
use App\Services\EstructuraOrganizacionalService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class UserUnidadAccesoController extends Controller
{
    public function index(Request $request, EstructuraOrganizacionalService $estructura, AccesoOperativoService $service): View
    {
        Gate::authorize('viewAny', UserUnidadAcceso::class);
        $fecha = $request->date('fecha')?->toDateString() ?? now()->toDateString();
        $q = UserUnidadAcceso::query()->with(['user.persona', 'unidad', 'creadoPor'])->when($request->integer('unidad_id'), fn (Builder $q, int $id) => $q->where('unidad_organizacional_id', $id))->when($request->filled('alcance'), fn (Builder $q) => $q->where('alcance', $request->string('alcance')))->when($request->filled('usuario'), function (Builder $q) use ($request): void {
            $termino = '%'.$request->string('usuario').'%';

            $q->whereHas('user', fn (Builder $u) => $u
                ->where('name', 'like', $termino)
                ->orWhere('email', 'like', $termino)
                ->orWhere('rut', 'like', $termino)
                ->orWhereHas('persona', fn (Builder $persona) => $persona->where('rut', 'like', $termino)));
        });
        if (! $request->boolean('incluir_historicos')) {
            $q->vigentesEn($fecha);
        }

        return view('admin.accesos.index', ['accesos' => $q->orderByDesc('vigente_desde')->get(), 'unidades' => UnidadOrganizacional::orderBy('nombre')->get(), 'alcances' => AlcanceAccesoOperativo::cases(), 'fecha' => $fecha, 'estructura' => $estructura, 'service' => $service]);
    }

    public function create(EstructuraOrganizacionalService $estructura): View
    {
        Gate::authorize('create', UserUnidadAcceso::class);

        return $this->form(null, $estructura);
    }

    public function store(SaveUserUnidadAccesoRequest $request, AccesoOperativoService $service): RedirectResponse
    {
        $service->crear($request->validated(), $request->user());

        return redirect()->route('admin.accesos.index')->with('status', 'Acceso registrado.');
    }

    public function edit(UserUnidadAcceso $acceso, EstructuraOrganizacionalService $estructura): View
    {
        Gate::authorize('update', $acceso);

        return $this->form($acceso, $estructura);
    }

    public function update(SaveUserUnidadAccesoRequest $request, UserUnidadAcceso $acceso, AccesoOperativoService $service): RedirectResponse
    {
        Gate::authorize('update', $acceso);
        $service->actualizar($acceso, $request->validated(), $request->user());

        return redirect()->route('admin.accesos.index')->with('status', 'Acceso actualizado.');
    }

    public function close(Request $request, UserUnidadAcceso $acceso, AccesoOperativoService $service): RedirectResponse
    {
        Gate::authorize('update', $acceso);
        $data = $request->validate(['vigente_hasta' => ['required', 'date', 'after_or_equal:'.$acceso->vigente_desde->toDateString()]]);
        $service->cerrarAcceso($acceso, $data['vigente_hasta'], $request->user());

        return back()->with('status', 'Acceso cerrado.');
    }

    private function form(?UserUnidadAcceso $acceso, EstructuraOrganizacionalService $estructura): View
    {
        return view('admin.accesos.form', ['acceso' => $acceso, 'alcances' => AlcanceAccesoOperativo::cases(), 'users' => User::with('persona')->where('active', true)->whereNotNull('persona_id')->orderBy('name')->get(), 'unidades' => UnidadOrganizacional::where('activo', true)->orderBy('nombre')->get()->map(fn ($u) => ['id' => $u->id, 'ruta' => $estructura->ruta($u)])]);
    }
}
