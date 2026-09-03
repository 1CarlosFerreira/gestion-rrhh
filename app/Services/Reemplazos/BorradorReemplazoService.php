<?php

namespace App\Services\Reemplazos;

use App\Actions\Tramites\GenerarCodigoTramite;
use App\Models\EstadoTramite;
use App\Models\Persona;
use App\Models\TipoReemplazo;
use App\Models\TipoTramite;
use App\Models\Tramite;
use App\Models\UnidadOrganizacional;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BorradorReemplazoService
{
    public function __construct(private readonly ReemplazoService $reemplazos, private readonly GenerarCodigoTramite $codigo) {}

    public function crear(UnidadOrganizacional $unidad, array $datos, User $actor): Tramite
    {
        return DB::transaction(function () use ($unidad, $datos, $actor): Tramite {
            $tipo = TipoTramite::query()->where('codigo', 'REEMPLAZO')->where('activo', true)->firstOrFail();
            $estado = EstadoTramite::query()->where('tipo_tramite_id', $tipo->id)->where('codigo', 'BORRADOR')->where('activo', true)->firstOrFail();
            $this->validarDatos($unidad, $datos);
            $tramite = Tramite::query()->create(['public_id' => (string) Str::ulid(), 'codigo' => $this->codigo->execute(), 'tipo_tramite_id' => $tipo->id, 'estado_tramite_id' => $estado->id, 'unidad_organizacional_id' => $unidad->id, 'created_by' => $actor->id]);
            $tramite->reemplazo()->create($datos);
            $tramite->historial()->create(['user_id' => $actor->id, 'action_code' => 'TRAMITE_CREADO', 'to_estado_id' => $estado->id, 'occurred_at' => now()]);

            return $tramite->load('reemplazo');
        });
    }

    public function actualizar(Tramite $tramite, UnidadOrganizacional $unidad, array $datos, User $actor): Tramite
    {
        return DB::transaction(function () use ($tramite, $unidad, $datos, $actor): Tramite {
            $tramite = Tramite::query()->lockForUpdate()->with(['reemplazo', 'estadoTramite'])->findOrFail($tramite->id);
            if ($tramite->estadoTramite->codigo !== 'BORRADOR') {
                throw ValidationException::withMessages(['tramite' => 'Solo se pueden editar solicitudes en borrador.']);
            }
            $this->validarDatos($unidad, $datos, $tramite->reemplazo->id);
            $tramite->update(['unidad_organizacional_id' => $unidad->id]);
            $tramite->reemplazo->update($datos);
            $tramite->historial()->create(['user_id' => $actor->id, 'action_code' => 'BORRADOR_ACTUALIZADO', 'metadata' => ['unidad_organizacional_id' => $unidad->id], 'occurred_at' => now()]);

            return $tramite->refresh()->load('reemplazo');
        });
    }

    private function validarDatos(UnidadOrganizacional $unidad, array $datos, ?int $exceptoId = null): void
    {
        if (! $unidad->activo) {
            throw ValidationException::withMessages(['unidad_organizacional_id' => 'La unidad debe estar activa.']);
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
        if (! empty($datos['funcionario_id']) && ! empty($datos['fecha_funcionario_desde']) && ! empty($datos['fecha_funcionario_hasta']) && $this->reemplazos->existeSuperposicion($datos['funcionario_id'], $datos['fecha_funcionario_desde'], $datos['fecha_funcionario_hasta'], $exceptoId)) {
            throw ValidationException::withMessages(['fecha_funcionario_desde' => 'El funcionario ya posee otro reemplazo con un período superpuesto.']);
        }
    }
}
