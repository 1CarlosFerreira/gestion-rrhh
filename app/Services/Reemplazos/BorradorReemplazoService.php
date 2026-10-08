<?php

namespace App\Services\Reemplazos;

use App\Actions\Tramites\GenerarCodigoTramite;
use App\Enums\ModalidadSolicitudContrato;
use App\Models\EstadoTramite;
use App\Models\Persona;
use App\Models\TipoReemplazo;
use App\Models\TipoTramite;
use App\Models\Tramite;
use App\Models\UnidadOrganizacional;
use App\Models\User;
use App\Services\SolicitudesContrato\ContextoSolicitudContratoService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BorradorReemplazoService
{
    private const CAMPOS_DETALLE = ['funcionario_id', 'reemplazante_id', 'reemplazante_estamento_id', 'reemplazante_profesion_id', 'reemplazante_calidad_contractual_id', 'reemplazante_cargo_funcion', 'tipo_reemplazo_id', 'fecha_funcionario_desde', 'fecha_funcionario_hasta', 'fecha_reemplazante_desde', 'fecha_reemplazante_hasta', 'justificacion'];

    public function __construct(
        private readonly ReemplazoService $reemplazos,
        private readonly GenerarCodigoTramite $codigo,
        private readonly ReemplazoWorkflow $workflow,
        private readonly ContextoSolicitudContratoService $contextoSolicitud,
        private readonly AlcanceSolicitudReemplazoService $alcance,
    ) {}

    public function crear(array $contexto, array $datos, User $actor): Tramite
    {
        return DB::transaction(function () use ($contexto, $datos, $actor): Tramite {
            Gate::forUser($actor)->authorize('crear-reemplazo');
            $contexto = $this->contextoSolicitud->prepararTransitoria($contexto, $actor, now());
            $datos = $this->filtrarDatos($datos);
            $tipo = TipoTramite::query()->where('codigo', 'REEMPLAZO')->where('activo', true)->firstOrFail();
            $estado = EstadoTramite::query()->where('tipo_tramite_id', $tipo->id)->where('codigo', 'BORRADOR')->where('activo', true)->firstOrFail();
            $this->validarDatos($contexto['unidad_origen'], $datos);
            $tramite = Tramite::query()->create(['public_id' => (string) Str::ulid(), 'codigo' => $this->codigo->execute(), 'tipo_tramite_id' => $tipo->id, 'estado_tramite_id' => $estado->id, 'unidad_organizacional_id' => $contexto['unidad_solicitante']->id, 'created_by' => $actor->id]);
            $this->guardarContexto($tramite, $contexto);
            $tramite->reemplazo()->create($datos);
            $tramite->historial()->create(['user_id' => $actor->id, 'action_code' => 'TRAMITE_CREADO', 'to_estado_id' => $estado->id, 'metadata' => $this->metadataContexto($contexto), 'occurred_at' => now()]);

            return $tramite->load(['solicitudContrato', 'reemplazo']);
        });
    }

    public function actualizar(Tramite $tramite, array|UnidadOrganizacional $contexto, array $datos, User $actor): Tramite
    {
        return DB::transaction(function () use ($tramite, $contexto, $datos, $actor): Tramite {
            $tramite = Tramite::query()->lockForUpdate()->with(['reemplazo', 'estadoTramite', 'solicitudContrato'])->findOrFail($tramite->id);
            Gate::forUser($actor)->authorize('editar-reemplazo', $tramite);
            if (! in_array($tramite->estadoTramite->codigo, ['BORRADOR', 'DEVUELTA_PARA_CORRECCION'], true)) {
                throw ValidationException::withMessages(['tramite' => 'La solicitud no se encuentra en un estado editable.']);
            }
            if ($tramite->solicitudContrato === null) {
                if (! ($contexto instanceof UnidadOrganizacional)) {
                    throw ValidationException::withMessages(['unidad_organizacional_id' => 'Un trámite V2 histórico requiere su unidad V2.']);
                }

                return $this->actualizarV2($tramite, $contexto, $datos, $actor);
            }
            if (! is_array($contexto)) {
                throw ValidationException::withMessages(['unidad_organizacional_id' => 'Una solicitud V3 requiere sus tres unidades explícitas.']);
            }
            $contexto = $this->contextoSolicitud->prepararTransitoria($contexto, $actor, now());
            $datos = $this->filtrarDatos($datos);
            $this->validarDatos($contexto['unidad_origen'], $datos, $tramite->reemplazo->id);
            $tramite->update(['unidad_organizacional_id' => $contexto['unidad_solicitante']->id]);
            $this->guardarContexto($tramite, $contexto);
            $tramite->reemplazo->update($datos);
            $tramite->historial()->create(['user_id' => $actor->id, 'action_code' => 'BORRADOR_ACTUALIZADO', 'metadata' => $this->metadataContexto($contexto), 'occurred_at' => now()]);

            return $tramite->refresh()->load(['solicitudContrato', 'reemplazo']);
        });
    }

    private function validarDatos(UnidadOrganizacional $unidad, array $datos, ?int $exceptoId = null): void
    {
        if (! $unidad->activo) {
            throw ValidationException::withMessages(['unidad_origen_id' => 'La unidad origen debe estar activa.']);
        }
        if (! empty($datos['funcionario_id'])) {
            $fecha = $datos['fecha_funcionario_desde'] ?? today();
            $pertenece = Persona::query()->whereKey($datos['funcionario_id'])->where('active', true)->whereHas('vinculosDotacion', fn ($q) => $q->where('unidad_organizacional_id', $unidad->id)->vigentesEn($fecha))->exists();
            if (! $pertenece) {
                throw ValidationException::withMessages(['funcionario_id' => 'El funcionario debe pertenecer a la dotación vigente de la unidad en la fecha inicial.']);
            }
        }
        if (! empty($datos['tipo_reemplazo_id']) && ! TipoReemplazo::query()->whereKey($datos['tipo_reemplazo_id'])->where('activo', true)->exists()) {
            throw ValidationException::withMessages(['tipo_reemplazo_id' => 'El tipo de reemplazo debe estar activo.']);
        }
        $this->reemplazos->validarBorrador($datos);
        if (! empty($datos['funcionario_id']) && ! empty($datos['fecha_funcionario_desde']) && ! empty($datos['fecha_funcionario_hasta']) && $this->reemplazos->existeSuperposicion($datos['funcionario_id'], $datos['fecha_funcionario_desde'], $datos['fecha_funcionario_hasta'], $exceptoId, fn ($q) => $this->workflow->filtrarActivos($q))) {
            throw ValidationException::withMessages(['fecha_funcionario_desde' => 'El funcionario ya posee otro reemplazo con un período superpuesto.']);
        }
    }

    private function guardarContexto(Tramite $tramite, array $contexto): void
    {
        $solicitud = $tramite->solicitudContrato()->firstOrNew();
        $solicitud->forceFill([
            'modalidad' => ModalidadSolicitudContrato::TRANSITORIA,
            'unidad_solicitante_id' => $contexto['unidad_solicitante']->id,
            'unidad_origen_id' => $contexto['unidad_origen']->id,
            'unidad_destino_id' => $contexto['unidad_destino']->id,
        ]);
        $solicitud->forceFill([
            'autoridad_persona_id' => $contexto['autoridad']['persona_id'],
            'autoridad_responsabilidad_id' => $contexto['autoridad']['responsabilidad_id'],
            'autoridad_contexto' => $contexto['autoridad_contexto'],
            'autoridad_resuelta_at' => now(),
        ]);
        $solicitud->save();
    }

    private function metadataContexto(array $contexto): array
    {
        return [
            'modalidad' => ModalidadSolicitudContrato::TRANSITORIA->value,
            'unidad_solicitante_id' => $contexto['unidad_solicitante']->id,
            'autoridad_persona_id' => $contexto['autoridad']['persona_id'],
            'autoridad_responsabilidad_id' => $contexto['autoridad']['responsabilidad_id'],
            'autoridad_responsabilidad_ids' => $contexto['autoridad']['responsabilidad_ids'],
            'unidad_origen_id' => $contexto['unidad_origen']->id,
            'unidad_destino_id' => $contexto['unidad_destino']->id,
        ];
    }

    private function actualizarV2(Tramite $tramite, UnidadOrganizacional $unidad, array $datos, User $actor): Tramite
    {
        $this->autorizarUnidadV2($actor, $unidad);
        $datos = $this->filtrarDatos($datos);
        $this->validarDatos($unidad, $datos, $tramite->reemplazo->id);
        $tramite->update(['unidad_organizacional_id' => $unidad->id]);
        $tramite->reemplazo->update($datos);
        $tramite->historial()->create(['user_id' => $actor->id, 'action_code' => 'BORRADOR_ACTUALIZADO', 'metadata' => ['unidad_organizacional_id' => $unidad->id], 'occurred_at' => now()]);

        return $tramite->refresh()->load('reemplazo');
    }

    private function autorizarUnidadV2(User $actor, UnidadOrganizacional $unidad): void
    {
        if (! $this->alcance->tienePermisoYAlcance($actor, 'reemplazos.crear', $unidad, today())) {
            throw new AuthorizationException('No está autorizado para gestionar reemplazos en esta unidad.');
        }
    }

    private function filtrarDatos(array $datos): array
    {
        return collect($datos)->only(self::CAMPOS_DETALLE)->all();
    }
}
