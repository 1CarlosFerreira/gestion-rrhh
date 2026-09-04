<?php

namespace App\Http\Controllers;

use App\Actions\Tramites\TransicionarTramite;
use App\Http\Requests\SaveRevisionReemplazoRequest;
use App\Models\CalidadContractual;
use App\Models\ClasificacionArea;
use App\Models\Estamento;
use App\Models\Profesion;
use App\Models\Tramite;
use App\Services\Accesos\AccesoOperativoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class GestionPersonasReemplazoController extends Controller
{
    public function index(Request $request, AccesoOperativoService $accesos): View
    {
        abort_unless($request->user()->can('reemplazos.revisar'), 403);
        $ids = $accesos->unidadesAccesibles($request->user(), today())->pluck('id');
        $tramites = Tramite::query()->with(['unidadOrganizacional', 'estadoTramite', 'reemplazo.funcionario', 'reemplazo.reemplazante'])->whereHas('tipoTramite', fn ($q) => $q->where('codigo', 'REEMPLAZO'))->whereIn('unidad_organizacional_id', $ids)->whereHas('estadoTramite', fn ($q) => $q->whereIn('codigo', ['ENVIADA_GESTION_PERSONAS', 'EN_REVISION', 'LISTA_GENERAR_DOCUMENTO', 'DOCUMENTO_GENERADO']))->orderBy('submitted_at')->paginate(20);

        return view('reemplazos.bandeja-revision', compact('tramites'));
    }

    public function show(Tramite $tramite): View
    {
        $this->authorizeReview($tramite);
        $tramite->load(['unidadOrganizacional', 'estadoTramite', 'creador', 'reemplazo.funcionario', 'reemplazo.reemplazante', 'reemplazo.tipoReemplazo', 'revisionReemplazo.clasificacionArea', 'formalizacionReemplazo.estamento', 'formalizacionReemplazo.profesion', 'formalizacionReemplazo.calidadContractual', 'formalizacionReemplazo.adjunto', 'formalizacionReemplazo.formalizadoPor', 'vinculoDotacion', 'adjuntos.tipoDocumento', 'historial.usuario', 'documentosGenerados.adjunto', 'documentosGenerados.generadoPor']);

        if (in_array($tramite->estadoTramite->codigo, ['LISTA_GENERAR_DOCUMENTO', 'DOCUMENTO_GENERADO', 'FORMALIZADA'], true)) {
            $estamentos = Estamento::query()->where('activo', true)->orderBy('nombre')->get();
            $profesiones = Profesion::query()->with('estamento')->where('activo', true)->orderBy('nombre')->get();
            $calidades = CalidadContractual::query()->where('activo', true)->orderBy('orden')->orderBy('nombre')->get();
            $calidadReemplazoId = $calidades->firstWhere('codigo', 'REEMPLAZO')?->id;

            return view('reemplazos.documento', compact('tramite', 'estamentos', 'profesiones', 'calidades', 'calidadReemplazoId'));
        }

        return view('reemplazos.revision', ['tramite' => $tramite, 'clasificaciones' => ClasificacionArea::query()->where('activo', true)->orderBy('nombre')->get()]);
    }

    public function start(Request $request, Tramite $tramite, TransicionarTramite $transition): RedirectResponse
    {
        $this->authorizeReview($tramite);
        $transition->execute($tramite, 'INICIAR_REVISION', $request->user());

        return redirect()->route('gestion-personas.reemplazos.show', $tramite)->with('status', 'Revisión iniciada.');
    }

    public function save(SaveRevisionReemplazoRequest $request, Tramite $tramite): RedirectResponse
    {
        $this->authorizeReview($tramite);
        abort_unless($tramite->estadoTramite?->codigo === 'EN_REVISION', 422);
        $tramite->revisionReemplazo()->updateOrCreate([], $request->validated());

        return back()->with('status', 'Antecedentes administrativos guardados.');
    }

    public function return(Request $request, Tramite $tramite, TransicionarTramite $transition): RedirectResponse
    {
        $this->authorizeReview($tramite);
        $observation = $request->validate(['observation' => ['required', 'string', 'max:5000']])['observation'];
        $transition->execute($tramite, 'DEVOLVER_PARA_CORRECCION', $request->user(), $observation);

        return redirect()->route('gestion-personas.reemplazos.index')->with('status', 'Solicitud devuelta para corrección.');
    }

    public function approve(Request $request, Tramite $tramite, TransicionarTramite $transition): RedirectResponse
    {
        $this->authorizeReview($tramite);
        DB::transaction(function () use ($request, $tramite, $transition): void {
            $transition->execute($tramite, 'APROBAR_ANTECEDENTES', $request->user());
            $tramite->revisionReemplazo()->update(['revisado_por' => $request->user()->id, 'revisado_at' => now()]);
        });

        return redirect()->route('gestion-personas.reemplazos.index')->with('status', 'Antecedentes aprobados.');
    }

    private function authorizeReview(Tramite $tramite): void
    {
        abort_unless($tramite->tipoTramite()->where('codigo', 'REEMPLAZO')->exists() && $tramite->reemplazo()->exists(), 404);
        abort_unless(Gate::allows('revisar-reemplazo', $tramite) || Gate::allows('formalizar-reemplazo', $tramite), 403);
    }
}
