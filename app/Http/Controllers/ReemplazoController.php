<?php

namespace App\Http\Controllers;

use App\Actions\Reemplazos\ActualizarGradoEusPendiente;
use App\Actions\Reemplazos\AgregarCoberturaAusencia;
use App\Actions\Reemplazos\CerrarAusenciaReemplazable;
use App\Actions\Reemplazos\CompletarRevisionReemplazo;
use App\Actions\Reemplazos\CrearReemplazo;
use App\Actions\Reemplazos\EnviarReemplazo;
use App\Actions\Reemplazos\GenerarSolicitudReemplazoPdfAction;
use App\Actions\Reemplazos\GuardarBorradorReemplazo;
use App\Actions\Reemplazos\GuardarRevisionReemplazo;
use App\Actions\Reemplazos\ReemplazoTransitionGuard;
use App\Actions\Tramites\Adjuntos\CargarAdjunto;
use App\Actions\Tramites\TransicionarTramite;
use App\Http\Requests\SaveReemplazoRequest;
use App\Http\Requests\SaveRevisionReemplazoRequest;
use App\Http\Requests\UpdateGradoEusReemplazoRequest;
use App\Models\AusenciaReemplazable;
use App\Models\ClasificacionArea;
use App\Models\Estamento;
use App\Models\GradoEus;
use App\Models\Persona;
use App\Models\Profesion;
use App\Models\TipoDocumento;
use App\Models\TipoReemplazo;
use App\Models\Tramite;
use App\Models\UnidadServicio;
use App\Services\Reemplazos\CoberturaAusenciaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class ReemplazoController extends Controller
{
    public function create(Request $request): View
    {
        abort_unless($request->user()->can('reemplazos.crear'), 403);
        $unidades = $request->user()->can('tramites.ver_todos') ? UnidadServicio::query()->where('activo', true)->orderBy('nombre')->get() : $request->user()->unidadesHabilitadas()->where('activo', true)->orderBy('nombre')->get();
        $unidadPreseleccionada = $unidades->count() === 1 ? $unidades->first() : null;
        $unidadSolicitada = $unidades->firstWhere('id', $request->integer('unidad_servicio_id'));
        $unidadInicial = $unidadSolicitada ?? $unidadPreseleccionada;
        $funcionarioPreseleccionado = null;
        if ($unidadInicial && $request->integer('funcionario_id')) {
            $today = now()->toDateString();
            $funcionarioPreseleccionado = Persona::query()->whereKey($request->integer('funcionario_id'))->where('active', true)->whereHas('vinculos', fn ($query) => $query->where('unidad_servicio_id', $unidadInicial->id)->where('status', 'ACTIVO')->where(fn ($query) => $query->whereNull('start_date')->orWhere('start_date', '<=', $today))->where(fn ($query) => $query->whereNull('end_date')->orWhere('end_date', '>=', $today)))->value('id');
        }

        return view('reemplazos.create', [
            'unidades' => $unidades,
            'unidadPreseleccionada' => $unidadPreseleccionada,
            'unidadInicial' => $unidadInicial,
            'funcionarioPreseleccionado' => $funcionarioPreseleccionado,
            ...$this->catalogsForUnits($unidades->pluck('id')->all()),
        ]);
    }

    public function store(SaveReemplazoRequest $request, CrearReemplazo $crear, CargarAdjunto $cargarAdjunto): RedirectResponse
    {
        $tramite = $crear->execute(UnidadServicio::query()->findOrFail($request->integer('unidad_servicio_id')), $request->user(), $request->validated());

        if ($request->hasFile('archivo')) {
            try {
                $cargarAdjunto->execute($tramite, $request->file('archivo'), $request->user(), $request->integer('tipo_documento_id') ?: null);

                return redirect()->route('reemplazos.edit', $tramite)->with('status', 'Borrador creado correctamente y documento adjuntado.');
            } catch (Throwable $exception) {
                report($exception);

                return redirect()->route('reemplazos.edit', $tramite)->with('upload_error', 'El borrador fue guardado, pero el documento no pudo adjuntarse.');
            }
        }

        return redirect()->route('reemplazos.edit', $tramite)->with('status', 'Borrador creado correctamente.');
    }

    public function edit(Tramite $tramite, ReemplazoTransitionGuard $guard): View
    {
        Gate::authorize('view', $tramite);
        abort_unless($tramite->tipoTramite()->value('codigo') === 'REEMPLAZO' && auth()->user()->can('reemplazos.crear') && in_array($tramite->estadoTramite->codigo, ['BORRADOR', 'DEVUELTA_CORRECCION'], true), 403);
        $tramite->load(['reemplazo.ausencia', 'reemplazo.funcionario', 'reemplazo.funcionarioVinculo', 'reemplazo.reemplazante', 'adjuntos.tipoDocumento']);

        return view('reemplazos.edit', ['tramite' => $tramite, 'sendErrors' => $guard->sendErrors($tramite), ...$this->catalogs($tramite)]);
    }

    public function update(SaveReemplazoRequest $request, Tramite $tramite, GuardarBorradorReemplazo $guardar, EnviarReemplazo $enviar): RedirectResponse
    {
        $guardar->execute($tramite->load(['estadoTramite', 'reemplazo']), $request->validated(), $request->user());

        if ($request->input('accion') === 'enviar') {
            try {
                $enviar->execute($tramite->fresh(), $request->user());

                return redirect()->route('tramites.show', $tramite)->with('status', 'Solicitud enviada correctamente a Gestión de Personas.');
            } catch (ValidationException $exception) {
                return redirect()->route('reemplazos.edit', $tramite)->withErrors($exception->errors())->withInput()->with('send_error', 'No fue posible enviar la solicitud. Revisa los siguientes antecedentes:');
            }
        }

        return back()->with('status', $tramite->estadoTramite->codigo === 'DEVUELTA_CORRECCION'
            ? 'Cambios guardados correctamente.'
            : 'Borrador guardado correctamente.');
    }

    public function send(Request $request, Tramite $tramite, EnviarReemplazo $enviar): RedirectResponse
    {
        try {
            $enviar->execute($tramite, $request->user());
        } catch (ValidationException $exception) {
            return redirect()->route('reemplazos.edit', $tramite)->withErrors($exception->errors())->with('send_error', 'No fue posible enviar la solicitud. Revisa los siguientes antecedentes:');
        }

        return redirect()->route('tramites.show', $tramite)->with('status', 'Solicitud enviada correctamente a Gestión de Personas.');
    }

    public function startReview(Request $request, Tramite $tramite, TransicionarTramite $transition): RedirectResponse
    {
        abort_unless($request->user()->can('reemplazos.revisar_personal'), 403);
        Gate::authorize('view', $tramite);
        try {
            $transition->execute($tramite, 'INICIAR_REVISION', $request->user());
        } catch (ValidationException $exception) {
            return redirect()->route('gestion-personas.bandeja')->withErrors($exception->errors());
        }

        return redirect()->route('reemplazos.review.show', $tramite)->with('status', 'Revisión iniciada correctamente.');
    }

    public function showReview(Request $request, Tramite $tramite): View
    {
        Gate::authorize('view', $tramite);
        abort_unless(
            $request->user()->can('reemplazos.revisar_personal')
            && $tramite->tipoTramite()->value('codigo') === 'REEMPLAZO'
            && $tramite->estadoTramite->codigo === 'EN_REVISION',
            403,
        );

        $tramite->load([
            'tipoTramite', 'unidadServicio', 'estadoTramite', 'creador',
            'reemplazo.ausencia.coberturas.tramite.estadoTramite', 'reemplazo.ausencia.coberturas.reemplazante', 'reemplazo.tipoReemplazo', 'reemplazo.funcionario', 'reemplazo.reemplazante', 'reemplazo.estamento', 'reemplazo.profesion',
            'revisionReemplazo.gradoEus', 'revisionReemplazo.clasificacionArea',
            'adjuntos.tipoDocumento', 'adjuntos.cargadoPor',
            'historial.usuario', 'historial.estadoOrigen', 'historial.estadoDestino',
        ]);

        return view('gestion-personas.reemplazos.revision', [
            'tramite' => $tramite,
            'grados' => GradoEus::query()->where('activo', true)->orderBy('grado')->get(),
            'clasificaciones' => ClasificacionArea::query()->where('activo', true)->orderBy('nombre')->get(),
            'requisitosDocumentales' => $this->requisitosDocumentales($tramite),
        ]);
    }

    public function saveReview(SaveRevisionReemplazoRequest $request, Tramite $tramite, GuardarRevisionReemplazo $guardar, CompletarRevisionReemplazo $complete, GenerarSolicitudReemplazoPdfAction $generar): RedirectResponse|JsonResponse
    {
        $changed = $guardar->execute($tramite->load('estadoTramite'), $request->validated(), $request->user());

        if ($request->input('accion') === 'aprobar') {
            try {
                $complete->execute($tramite->fresh(), $request->user());
            } catch (ValidationException $exception) {
                if ($request->expectsJson()) {
                    return response()->json([
                        'message' => 'Revisa los antecedentes ingresados.',
                        'errors' => $exception->errors(),
                    ], 422);
                }

                return redirect()->route('reemplazos.review.show', $tramite)->withErrors($exception->errors())->withInput();
            }

            try {
                $generar->execute($tramite->fresh(), $request->user());
            } catch (Throwable $exception) {
                report($exception);

                if ($request->expectsJson()) {
                    return response()->json([
                        'message' => 'Los antecedentes fueron guardados, pero no fue posible generar el PDF. Corrige los datos pendientes o intenta nuevamente.',
                        'redirect' => route('tramites.show', $tramite),
                    ], 500);
                }

                return redirect()->route('tramites.show', $tramite)->with('error', 'Los antecedentes fueron guardados, pero no fue posible generar el PDF. Corrige los datos pendientes o intenta nuevamente.');
            }

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Antecedentes aprobados y documento oficial generado correctamente.',
                    'redirect' => route('tramites.show', $tramite),
                ]);
            }

            return redirect()->route('tramites.show', $tramite)->with('status', 'Antecedentes aprobados y documento oficial generado correctamente.');
        }

        return back()->with('status', $changed ? 'Avance de revisión guardado correctamente.' : 'No hay cambios pendientes por guardar.');
    }

    public function returnCorrection(Request $request, Tramite $tramite, TransicionarTramite $transition): RedirectResponse
    {
        abort_unless($request->user()->can('reemplazos.devolver'), 403);
        Gate::authorize('view', $tramite);
        $validated = $request->validate(['observation' => ['required', 'string', 'max:5000']]);
        $transition->execute($tramite, 'DEVOLVER_CORRECCION', $request->user(), $validated['observation']);

        return redirect()->route('gestion-personas.bandeja')->with('status', 'Solicitud devuelta a Jefatura para corrección.');
    }

    public function completeReview(Request $request, Tramite $tramite, CompletarRevisionReemplazo $complete): RedirectResponse
    {
        abort_unless($request->user()->can('reemplazos.revisar_personal'), 403);
        $complete->execute($tramite, $request->user());

        return redirect()->route('gestion-personas.bandeja')->with('status', 'Revisión completada. La solicitud está lista para generar el documento.');
    }

    public function retryPdf(Request $request, Tramite $tramite, GenerarSolicitudReemplazoPdfAction $generar): RedirectResponse
    {
        try {
            $generar->execute($tramite, $request->user());
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('error', 'Los antecedentes fueron guardados, pero no fue posible generar el PDF. Corrige los datos pendientes o intenta nuevamente.');
        }

        return redirect()->route('tramites.show', $tramite)->with('status', 'Documento oficial generado correctamente.');
    }

    public function updatePendingGrade(UpdateGradoEusReemplazoRequest $request, Tramite $tramite, ActualizarGradoEusPendiente $actualizar): RedirectResponse
    {
        $actualizar->execute($tramite, $request->integer('grado_eus_informado'), $request->user());

        return back()->with('status', 'Último grado E.U.S. guardado correctamente. Ya puedes reintentar la generación.');
    }

    public function showAbsence(Request $request, AusenciaReemplazable $ausencia, CoberturaAusenciaService $coberturas): View
    {
        abort_unless($request->user()->can('tramites.ver_todos') || $request->user()->unidadesHabilitadas()->whereKey($ausencia->unidad_servicio_id)->exists(), 403);
        $ausencia->load(['funcionario', 'unidad', 'tipoReemplazo', 'creador', 'cerradoPor', 'historial.usuario', 'coberturas.tramite.estadoTramite', 'coberturas.tramite.documentosGenerados.adjunto', 'coberturas.reemplazante']);

        return view('reemplazos.ausencias.show', ['ausencia' => $ausencia, 'resumen' => $coberturas->summary($ausencia)]);
    }

    public function addCoverage(Request $request, AusenciaReemplazable $ausencia, AgregarCoberturaAusencia $agregar): RedirectResponse
    {
        try {
            $tramite = $agregar->execute($ausencia, $request->user());
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }

        return redirect()->route('reemplazos.edit', $tramite)->with('status', 'Nueva cobertura creada. Completa el reemplazante y confirma su periodo efectivo.');
    }

    public function closeAbsence(Request $request, AusenciaReemplazable $ausencia, CerrarAusenciaReemplazable $cerrar): RedirectResponse
    {
        $validated = $request->validate(['motivo' => ['required', 'string', 'max:5000']]);
        $cerrar->execute($ausencia, $validated['motivo'], $request->user());

        return back()->with('status', 'Ausencia cerrada correctamente. Las coberturas existentes se conservaron.');
    }

    private function catalogs(Tramite $tramite): array
    {
        return [
            ...$this->catalogsForUnits([$tramite->unidad_servicio_id]),
            'grados' => GradoEus::query()->where('activo', true)->orderBy('grado')->get(),
            'clasificaciones' => ClasificacionArea::query()->where('activo', true)->orderBy('nombre')->get(),
        ];
    }

    private function requisitosDocumentales(Tramite $tramite): Collection
    {
        $replacementTypeId = $tramite->reemplazo?->tipo_reemplazo_id;
        $attachedTypes = $tramite->adjuntos->where('status', 'ACTIVO')->pluck('tipo_documento_id');

        return DB::table('requisitos_documentales')
            ->join('tipos_documento', 'tipos_documento.id', '=', 'requisitos_documentales.tipo_documento_id')
            ->where('requisitos_documentales.tipo_tramite_id', $tramite->tipo_tramite_id)
            ->where('requisitos_documentales.active', true)
            ->where(fn ($query) => $query->whereNull('requisitos_documentales.tipo_reemplazo_id')->orWhere('requisitos_documentales.tipo_reemplazo_id', $replacementTypeId))
            ->where(fn ($query) => $query->whereNull('requisitos_documentales.valid_from')->orWhere('requisitos_documentales.valid_from', '<=', now()->toDateString()))
            ->where(fn ($query) => $query->whereNull('requisitos_documentales.valid_to')->orWhere('requisitos_documentales.valid_to', '>=', now()->toDateString()))
            ->orderBy('tipos_documento.nombre')
            ->get(['tipos_documento.nombre', 'requisitos_documentales.tipo_documento_id', 'requisitos_documentales.obligatorio'])
            ->map(fn ($requirement) => (object) [
                ...((array) $requirement),
                'cumplido' => $attachedTypes->contains($requirement->tipo_documento_id),
            ]);
    }

    private function catalogsForUnits(array $unitIds): array
    {
        $today = now()->toDateString();

        return [
            'tiposReemplazo' => TipoReemplazo::query()->where('activo', true)->orderBy('nombre')->get(),
            'estamentos' => Estamento::query()->where('activo', true)->orderBy('nombre')->get(),
            'profesiones' => Profesion::query()->where('activo', true)->orderBy('nombre')->get(),
            'funcionariosUnidad' => Persona::query()->where('active', true)
                ->with(['vinculos' => fn ($query) => $query->whereIn('unidad_servicio_id', $unitIds)->where('status', 'ACTIVO')->where(fn ($query) => $query->whereNull('start_date')->orWhere('start_date', '<=', $today))->where(fn ($query) => $query->whereNull('end_date')->orWhere('end_date', '>=', $today))->with(['estamento', 'profesion'])])
                ->whereHas('vinculos', fn ($query) => $query->whereIn('unidad_servicio_id', $unitIds)->where('status', 'ACTIVO')->where(fn ($query) => $query->whereNull('start_date')->orWhere('start_date', '<=', $today))->where(fn ($query) => $query->whereNull('end_date')->orWhere('end_date', '>=', $today)))
                ->orderBy('apellido_paterno')->orderBy('nombres')->get(),
            'personas' => Persona::query()->where('active', true)->with(['vinculos.unidad', 'vinculos.estamento', 'vinculos.profesion'])->orderBy('apellido_paterno')->orderBy('nombres')->get(),
            'tiposDocumento' => TipoDocumento::query()->where('active', true)->orderBy('nombre')->get(),
        ];
    }
}
