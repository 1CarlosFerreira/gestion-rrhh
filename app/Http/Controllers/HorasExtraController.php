<?php

namespace App\Http\Controllers;

use App\Actions\HorasExtraordinarias\CargarPlanillaSirh;
use App\Actions\HorasExtraordinarias\CrearHorasExtraordinarias;
use App\Actions\HorasExtraordinarias\FinalizarRevisionHorasExtra;
use App\Actions\HorasExtraordinarias\GuardarBorradorHorasExtra;
use App\Actions\HorasExtraordinarias\RegistrarHorasExtra;
use App\Actions\HorasExtraordinarias\RevisarPlanillaSirh;
use App\Actions\Tramites\TransicionarTramite;
use App\Http\Requests\RegisterHorasExtraRequest;
use App\Http\Requests\ReviewPlanillaSirhRequest;
use App\Http\Requests\SaveHorasExtraRequest;
use App\Models\HorasExtraFuncionario;
use App\Models\Persona;
use App\Models\Tramite;
use App\Models\UnidadServicio;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class HorasExtraController extends Controller
{
    public function create(Request $request): View
    {
        abort_unless($request->user()->can('horas_extra.crear'), 403);

        return view('horas-extra.create', $this->formData($request));
    }

    public function store(SaveHorasExtraRequest $request, CrearHorasExtraordinarias $crear): RedirectResponse
    {
        $tramite = $crear->execute(UnidadServicio::findOrFail($request->integer('unidad_servicio_id')), $request->integer('year'), $request->integer('month'), $request->input('persona_ids'), $request->user());

        return redirect()->route('horas-extra.edit', $tramite)->with('status', 'Borrador creado.');
    }

    public function edit(Request $request, Tramite $tramite): View
    {
        Gate::authorize('view', $tramite);
        abort_unless($request->user()->can('horas_extra.crear') && $tramite->tipoTramite()->value('codigo') === 'HORAS_EXTRAORDINARIAS' && $tramite->estadoTramite()->value('codigo') === 'BORRADOR', 403);
        $tramite->load('horasExtra.funcionarios');

        return view('horas-extra.edit', ['tramite' => $tramite, ...$this->formData($request)]);
    }

    public function update(SaveHorasExtraRequest $request, Tramite $tramite, GuardarBorradorHorasExtra $guardar): RedirectResponse
    {
        $guardar->execute($tramite, UnidadServicio::findOrFail($request->integer('unidad_servicio_id')), $request->integer('year'), $request->integer('month'), $request->input('persona_ids'), $request->user());

        return back()->with('status', 'Borrador guardado.');
    }

    public function transition(Request $request, Tramite $tramite, TransicionarTramite $transition, string $action): RedirectResponse
    {
        Gate::authorize('view', $tramite);
        $allowed = ['ENVIAR_A_GESTION_PERSONAS', 'PUBLICAR_PLANILLAS', 'INICIAR_REVISION_JEFATURA', 'INICIAR_CORRECCION_SIRH', 'VOLVER_A_PLANILLA_DISPONIBLE'];
        abort_unless(in_array($action, $allowed, true), 404);
        $transition->execute($tramite, $action, $request->user());

        return back()->with('status', 'El trámite avanzó correctamente.');
    }

    public function upload(Request $request, Tramite $tramite, HorasExtraFuncionario $funcionario, CargarPlanillaSirh $upload): RedirectResponse
    {
        $this->ensureParticipant($tramite, $funcionario);
        $validated = $request->validate(['archivo' => ['required', 'file', 'mimetypes:application/pdf', 'max:20480']]);
        $upload->execute($funcionario, $validated['archivo'], $request->user());

        return back()->with('status', 'Planilla SIRH cargada.');
    }

    public function hours(RegisterHorasExtraRequest $request, Tramite $tramite, HorasExtraFuncionario $funcionario, RegistrarHorasExtra $register): RedirectResponse
    {
        $this->ensureParticipant($tramite, $funcionario);
        $register->execute($funcionario, $request->integer('day_hours'), $request->integer('day_minutes'), $request->integer('night_hours'), $request->integer('night_minutes'), $request->user());

        return back()->with('status', 'Horas registradas en minutos.');
    }

    public function review(ReviewPlanillaSirhRequest $request, Tramite $tramite, HorasExtraFuncionario $funcionario, RevisarPlanillaSirh $review): RedirectResponse
    {
        $this->ensureParticipant($tramite, $funcionario);
        $review->execute($funcionario, $request->string('result')->toString(), $request->input('observation'), $request->user());

        return back()->with('status', 'Decisión registrada.');
    }

    public function finishReview(Request $request, Tramite $tramite, FinalizarRevisionHorasExtra $finish): RedirectResponse
    {
        $finish->execute($tramite, $request->user());

        return back()->with('status', 'Revisión de Jefatura finalizada.');
    }

    private function ensureParticipant(Tramite $tramite, HorasExtraFuncionario $funcionario): void
    {
        abort_unless($funcionario->tramiteHorasExtra()->where('tramite_id', $tramite->id)->exists(), 404);
    }

    private function formData(Request $request): array
    {
        $unidades = $request->user()->can('tramites.ver_todos') ? UnidadServicio::query()->where('activo', true)->orderBy('nombre')->get() : $request->user()->unidadesHabilitadas()->where('activo', true)->orderBy('nombre')->get();
        $personas = Persona::query()->with(['vinculosOperativos.unidad', 'vinculosOperativos.estamento', 'vinculosOperativos.profesion'])->where('active', true)->orderBy('apellido_paterno')->orderBy('nombres')->get();

        return compact('unidades', 'personas');
    }
}
