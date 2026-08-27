<?php

namespace App\Http\Controllers;

use App\Actions\Reemplazos\CompletarRevisionReemplazo;
use App\Actions\Reemplazos\CrearReemplazo;
use App\Actions\Reemplazos\EnviarReemplazo;
use App\Actions\Reemplazos\GuardarBorradorReemplazo;
use App\Actions\Reemplazos\GuardarRevisionReemplazo;
use App\Actions\Tramites\TransicionarTramite;
use App\Http\Requests\SaveReemplazoRequest;
use App\Http\Requests\SaveRevisionReemplazoRequest;
use App\Models\ClasificacionArea;
use App\Models\Estamento;
use App\Models\GradoEus;
use App\Models\Persona;
use App\Models\Profesion;
use App\Models\TipoReemplazo;
use App\Models\Tramite;
use App\Models\UnidadServicio;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ReemplazoController extends Controller
{
    public function create(Request $request): View
    {
        abort_unless($request->user()->can('reemplazos.crear'), 403);
        $unidades = $request->user()->can('tramites.ver_todos') ? UnidadServicio::query()->where('activo', true)->orderBy('nombre')->get() : $request->user()->unidadesHabilitadas()->where('activo', true)->orderBy('nombre')->get();

        return view('reemplazos.create', compact('unidades'));
    }

    public function store(SaveReemplazoRequest $request, CrearReemplazo $crear): RedirectResponse
    {
        $tramite = $crear->execute(UnidadServicio::query()->findOrFail($request->integer('unidad_servicio_id')), $request->user());

        return redirect()->route('reemplazos.edit', $tramite);
    }

    public function edit(Tramite $tramite): View
    {
        Gate::authorize('view', $tramite);
        abort_unless($tramite->tipoTramite()->value('codigo') === 'REEMPLAZO' && auth()->user()->can('reemplazos.crear') && in_array($tramite->estadoTramite->codigo, ['BORRADOR', 'DEVUELTA_CORRECCION'], true), 403);
        $tramite->load(['reemplazo.funcionario', 'reemplazo.funcionarioVinculo', 'reemplazo.reemplazante']);

        return view('reemplazos.edit', ['tramite' => $tramite, ...$this->catalogs($tramite)]);
    }

    public function update(SaveReemplazoRequest $request, Tramite $tramite, GuardarBorradorReemplazo $guardar): RedirectResponse
    {
        $guardar->execute($tramite->load(['estadoTramite', 'reemplazo']), $request->validated(), $request->user());

        return back()->with('status', 'Borrador guardado.');
    }

    public function send(Request $request, Tramite $tramite, EnviarReemplazo $enviar): RedirectResponse
    {
        $enviar->execute($tramite, $request->user());

        return redirect()->route('tramites.show', $tramite)->with('status', 'Solicitud enviada a Gestión de Personas.');
    }

    public function startReview(Request $request, Tramite $tramite, TransicionarTramite $transition): RedirectResponse
    {
        abort_unless($request->user()->can('reemplazos.revisar_personal'), 403);
        Gate::authorize('view', $tramite);
        $transition->execute($tramite, 'INICIAR_REVISION', $request->user());

        return back()->with('status', 'Revisión iniciada.');
    }

    public function saveReview(SaveRevisionReemplazoRequest $request, Tramite $tramite, GuardarRevisionReemplazo $guardar): RedirectResponse
    {
        $guardar->execute($tramite->load('estadoTramite'), $request->validated(), $request->user());

        return back()->with('status', 'Revisión administrativa guardada.');
    }

    public function returnCorrection(Request $request, Tramite $tramite, TransicionarTramite $transition): RedirectResponse
    {
        abort_unless($request->user()->can('reemplazos.devolver'), 403);
        Gate::authorize('view', $tramite);
        $validated = $request->validate(['observation' => ['required', 'string', 'max:5000']]);
        $transition->execute($tramite, 'DEVOLVER_CORRECCION', $request->user(), $validated['observation']);

        return back()->with('status', 'Solicitud devuelta para corrección.');
    }

    public function completeReview(Request $request, Tramite $tramite, CompletarRevisionReemplazo $complete): RedirectResponse
    {
        abort_unless($request->user()->can('reemplazos.revisar_personal'), 403);
        $complete->execute($tramite, $request->user());

        return back()->with('status', 'Revisión completada.');
    }

    private function catalogs(Tramite $tramite): array
    {
        $today = now()->toDateString();

        return [
            'tiposReemplazo' => TipoReemplazo::query()->where('activo', true)->orderBy('nombre')->get(),
            'estamentos' => Estamento::query()->where('activo', true)->orderBy('nombre')->get(),
            'profesiones' => Profesion::query()->where('activo', true)->orderBy('nombre')->get(),
            'grados' => GradoEus::query()->where('activo', true)->orderBy('grado')->get(),
            'clasificaciones' => ClasificacionArea::query()->where('activo', true)->orderBy('nombre')->get(),
            'funcionariosUnidad' => Persona::query()->where('active', true)
                ->with(['vinculos' => fn ($query) => $query->where('unidad_servicio_id', $tramite->unidad_servicio_id)->where('status', 'ACTIVO')->where(fn ($query) => $query->whereNull('start_date')->orWhere('start_date', '<=', $today))->where(fn ($query) => $query->whereNull('end_date')->orWhere('end_date', '>=', $today))->with(['estamento', 'profesion'])])
                ->whereHas('vinculos', fn ($query) => $query->where('unidad_servicio_id', $tramite->unidad_servicio_id)->where('status', 'ACTIVO')->where(fn ($query) => $query->whereNull('start_date')->orWhere('start_date', '<=', $today))->where(fn ($query) => $query->whereNull('end_date')->orWhere('end_date', '>=', $today)))
                ->orderBy('apellido_paterno')->orderBy('nombres')->get(),
            'personas' => Persona::query()->where('active', true)->with(['vinculos.unidad', 'vinculos.estamento', 'vinculos.profesion'])->orderBy('apellido_paterno')->orderBy('nombres')->get(),
        ];
    }
}
