<?php

namespace App\Http\Controllers;

use App\Actions\Tramites\TransicionarTramite;
use App\Http\Requests\SaveRevisionReemplazoRequest;
use App\Models\CalidadContractual;
use App\Models\ClasificacionArea;
use App\Models\EstadoTramite;
use App\Models\Estamento;
use App\Models\Profesion;
use App\Models\Tramite;
use App\Models\UnidadOrganizacional;
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
        $estadosBandeja = ['ENVIADA_GESTION_PERSONAS', 'EN_REVISION', 'LISTA_GENERAR_DOCUMENTO', 'DOCUMENTO_GENERADO'];
        $consultaAlcance = Tramite::query()
            ->whereHas('tipoTramite', fn ($q) => $q->where('codigo', 'REEMPLAZO'))
            ->when(! $request->user()->can('tramites.ver_todos'), fn ($q) => $q->whereIn('unidad_organizacional_id', $ids))
            ->whereHas('estadoTramite', fn ($q) => $q->whereIn('codigo', $estadosBandeja));

        $indicadores = collect([
            'pendientes' => 'ENVIADA_GESTION_PERSONAS',
            'en_revision' => 'EN_REVISION',
            'para_documento' => 'LISTA_GENERAR_DOCUMENTO',
        ])->map(fn ($estado) => (clone $consultaAlcance)->whereHas('estadoTramite', fn ($q) => $q->where('codigo', $estado))->count());

        $estados = EstadoTramite::query()
            ->whereHas('tipoTramite', fn ($q) => $q->where('codigo', 'REEMPLAZO'))
            ->whereIn('codigo', $estadosBandeja)
            ->orderBy('orden')
            ->get();
        $unidades = UnidadOrganizacional::query()
            ->whereIn('id', (clone $consultaAlcance)->select('unidad_organizacional_id'))
            ->orderBy('nombre')
            ->get();
        $haySolicitudes = (clone $consultaAlcance)->exists();
        $buscar = trim((string) $request->query('buscar'));
        $estado = (string) $request->query('estado');
        $unidadId = $request->integer('unidad_id');
        $terminosNombre = array_values(array_filter(preg_split('/\s+/', $buscar) ?: []));
        $buscarPersona = function ($persona) use ($buscar, $terminosNombre): void {
            $persona->where(function ($persona) use ($buscar, $terminosNombre): void {
                $persona->buscar($buscar)
                    ->orWhere(function ($nombre) use ($terminosNombre): void {
                        foreach ($terminosNombre as $termino) {
                            $nombre->where(function ($campo) use ($termino): void {
                                $campo->where('nombres', 'like', '%'.$termino.'%')
                                    ->orWhere('apellido_paterno', 'like', '%'.$termino.'%')
                                    ->orWhere('apellido_materno', 'like', '%'.$termino.'%');
                            });
                        }
                    });
            });
        };

        $consulta = (clone $consultaAlcance)
            ->when($buscar !== '', function ($query) use ($buscar, $buscarPersona): void {
                $query->where(function ($query) use ($buscar, $buscarPersona): void {
                    $query->where('codigo', 'like', '%'.$buscar.'%')
                        ->orWhereHas('reemplazo.funcionario', $buscarPersona)
                        ->orWhereHas('reemplazo.reemplazante', $buscarPersona);
                });
            })
            ->when(in_array($estado, $estadosBandeja, true), fn ($query) => $query->whereHas('estadoTramite', fn ($estadoQuery) => $estadoQuery->where('codigo', $estado)))
            ->when($unidadId > 0, fn ($query) => $query->where('unidad_organizacional_id', $unidadId));

        $tramites = $consulta
            ->with(['unidadOrganizacional', 'estadoTramite', 'reemplazo.funcionario', 'reemplazo.reemplazante'])
            ->orderBy('submitted_at')
            ->paginate(25)
            ->withQueryString();

        return view('reemplazos.bandeja-revision', compact('tramites', 'indicadores', 'estados', 'unidades', 'haySolicitudes'));
    }

    public function show(Tramite $tramite): View
    {
        $this->authorizeShow($tramite);
        $tramite->load(['unidadOrganizacional', 'estadoTramite', 'creador', 'reemplazo.funcionario', 'reemplazo.reemplazante', 'reemplazo.tipoReemplazo', 'revisionReemplazo.clasificacionArea', 'revisionReemplazo.revisadoPor', 'formalizacionReemplazo.estamento', 'formalizacionReemplazo.profesion', 'formalizacionReemplazo.calidadContractual', 'formalizacionReemplazo.adjunto', 'formalizacionReemplazo.formalizadoPor', 'vinculoDotacion', 'adjuntos.tipoDocumento', 'historial.usuario', 'documentosGenerados.adjunto', 'documentosGenerados.generadoPor']);
        $tramite->reemplazo->funcionario->load([
            'vinculosDotacion' => fn ($query) => $query
                ->with(['estamento', 'profesion'])
                ->where('unidad_organizacional_id', $tramite->unidad_organizacional_id)
                ->vigentesEn($tramite->reemplazo->fecha_funcionario_desde)
                ->orderByDesc('vigente_desde'),
        ]);

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
        $this->guardarRevision($tramite, $request->validated());

        return back()->with('status', 'Antecedentes administrativos guardados.');
    }

    public function return(Request $request, Tramite $tramite, TransicionarTramite $transition): RedirectResponse
    {
        $this->authorizeReview($tramite);
        $observation = $request->validate(['observation' => ['required', 'string', 'max:5000']])['observation'];
        $transition->execute($tramite, 'DEVOLVER_PARA_CORRECCION', $request->user(), $observation);

        return redirect()->route('gestion-personas.reemplazos.index')->with('status', 'Solicitud devuelta para corrección.');
    }

    public function approve(SaveRevisionReemplazoRequest $request, Tramite $tramite, TransicionarTramite $transition): RedirectResponse
    {
        $this->authorizeReview($tramite);
        DB::transaction(function () use ($request, $tramite, $transition): void {
            $this->guardarRevision($tramite, $request->validated());
            $transition->execute($tramite, 'APROBAR_ANTECEDENTES', $request->user());
            $tramite->revisionReemplazo()->update(['revisado_por' => $request->user()->id, 'revisado_at' => now()]);
        });

        return redirect()->route('gestion-personas.reemplazos.index')->with('status', 'Antecedentes aprobados.');
    }

    private function guardarRevision(Tramite $tramite, array $datos): void
    {
        $tramite->revisionReemplazo()->updateOrCreate([], $datos);
    }

    private function authorizeReview(Tramite $tramite): void
    {
        abort_unless($tramite->tipoTramite()->where('codigo', 'REEMPLAZO')->exists() && $tramite->reemplazo()->exists(), 404);
        abort_unless(Gate::allows('revisar-reemplazo', $tramite), 403);
    }

    private function authorizeShow(Tramite $tramite): void
    {
        abort_unless($tramite->tipoTramite()->where('codigo', 'REEMPLAZO')->exists() && $tramite->reemplazo()->exists(), 404);
        $esEtapaDocumental = in_array($tramite->estadoTramite?->codigo, ['LISTA_GENERAR_DOCUMENTO', 'DOCUMENTO_GENERADO', 'FORMALIZADA'], true);
        abort_unless(Gate::allows('revisar-reemplazo', $tramite) || ($esEtapaDocumental && Gate::allows('formalizar-reemplazo', $tramite)), 403);
    }
}
