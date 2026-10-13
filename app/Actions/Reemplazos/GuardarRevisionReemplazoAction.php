<?php

namespace App\Actions\Reemplazos;

use App\Models\CalidadContractual;
use App\Models\Estamento;
use App\Models\Profesion;
use App\Models\ReemplazoRevision;
use App\Models\Tramite;
use App\Models\TramiteReemplazo;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class GuardarRevisionReemplazoAction
{
    private const CAMPOS_CORREGIBLES = [
        'reemplazante_estamento_id',
        'reemplazante_profesion_id',
        'reemplazante_calidad_contractual_id',
        'reemplazante_cargo_funcion',
    ];

    public function execute(Tramite $tramite, array $datos, User $actor): ReemplazoRevision
    {
        return DB::transaction(function () use ($tramite, $datos, $actor): ReemplazoRevision {
            $tramite = Tramite::query()
                ->with(['tipoTramite', 'estadoTramite', 'unidadOrganizacional'])
                ->lockForUpdate()
                ->findOrFail($tramite->id);

            Gate::forUser($actor)->authorize('revisar-reemplazo', $tramite);

            if ($tramite->tipoTramite?->codigo !== 'REEMPLAZO' || ! $tramite->reemplazo()->exists()) {
                throw ValidationException::withMessages([
                    'revision' => 'El trámite no corresponde a una solicitud de reemplazo revisable.',
                ]);
            }

            if ($tramite->estadoTramite?->codigo !== 'EN_REVISION') {
                throw ValidationException::withMessages([
                    'revision' => 'La solicitud cambió de estado y su revisión ya no puede modificarse.',
                ]);
            }

            $revision = $tramite->revisionReemplazo()->updateOrCreate([], $this->validarDatos($datos));
            $this->corregirAntecedentesV3($tramite, $datos, $actor);

            return $revision;
        });
    }

    private function validarDatos(array $datos): array
    {
        return Validator::make($datos, [
            'grado_eus' => ['nullable', 'integer', 'min:1', 'max:99'],
            'clasificacion_area_id' => ['nullable', Rule::exists('clasificaciones_area', 'id')->where('activo', true)],
            'cumple_normativa' => ['nullable', 'boolean'],
            'observacion_administrativa' => ['nullable', 'string', 'max:5000'],
        ])->validate();
    }

    private function corregirAntecedentesV3(Tramite $tramite, array $datos, User $actor): void
    {
        $solicitudV3 = $tramite->solicitudContrato()->exists();
        $presentes = array_intersect(self::CAMPOS_CORREGIBLES, array_keys($datos));
        if (! $solicitudV3 && ($presentes !== [] || array_key_exists('motivo_correccion', $datos))) {
            throw ValidationException::withMessages(['revision' => 'Las correcciones de antecedentes corresponden solo a solicitudes V3.']);
        }
        if (! $solicitudV3 || $presentes === []) {
            return;
        }

        $detalle = TramiteReemplazo::query()->where('tramite_id', $tramite->id)->lockForUpdate()->firstOrFail();
        if ($detalle->reemplazante_id === null) {
            throw ValidationException::withMessages(['reemplazante_id' => 'La solicitud no tiene una persona propuesta cuyos antecedentes corregir.']);
        }

        $validados = Validator::make($datos, [
            'reemplazante_estamento_id' => ['sometimes', 'nullable', 'integer', Rule::exists('estamentos', 'id')->where('activo', true)],
            'reemplazante_profesion_id' => ['sometimes', 'nullable', 'integer', Rule::exists('profesiones', 'id')->where('activo', true)],
            'reemplazante_calidad_contractual_id' => ['sometimes', 'nullable', 'integer', Rule::exists('calidades_contractuales', 'id')->where('activo', true)],
            'reemplazante_cargo_funcion' => ['sometimes', 'nullable', 'string', 'max:200'],
            'motivo_correccion' => ['nullable', 'string', 'max:5000'],
        ])->validate();

        $nuevos = [];
        $cambios = [];
        foreach (self::CAMPOS_CORREGIBLES as $campo) {
            if (! array_key_exists($campo, $validados)) {
                continue;
            }
            $nuevo = $validados[$campo];
            $nuevo = $campo === 'reemplazante_cargo_funcion'
                ? (filled($nuevo) ? preg_replace('/\s+/u', ' ', trim($nuevo)) : null)
                : ($nuevo === null ? null : (int) $nuevo);
            $anterior = $detalle->$campo;
            if ($nuevo !== $anterior) {
                $nuevos[$campo] = $nuevo;
                $cambios[$campo] = [
                    'anterior' => $this->valorAuditoria($campo, $anterior),
                    'nuevo' => $this->valorAuditoria($campo, $nuevo),
                ];
            }
        }
        if ($cambios === []) {
            return;
        }
        if (blank($validados['motivo_correccion'] ?? null)) {
            throw ValidationException::withMessages(['motivo_correccion' => 'Indique el motivo de la corrección de antecedentes.']);
        }

        $estamentoId = array_key_exists('reemplazante_estamento_id', $nuevos) ? $nuevos['reemplazante_estamento_id'] : $detalle->reemplazante_estamento_id;
        $profesionId = array_key_exists('reemplazante_profesion_id', $nuevos) ? $nuevos['reemplazante_profesion_id'] : $detalle->reemplazante_profesion_id;
        $calidadId = array_key_exists('reemplazante_calidad_contractual_id', $nuevos) ? $nuevos['reemplazante_calidad_contractual_id'] : $detalle->reemplazante_calidad_contractual_id;
        $cargo = array_key_exists('reemplazante_cargo_funcion', $nuevos) ? $nuevos['reemplazante_cargo_funcion'] : $detalle->reemplazante_cargo_funcion;
        if ($estamentoId === null || $calidadId === null || blank($cargo)) {
            throw ValidationException::withMessages(['revision' => 'La persona propuesta debe conservar estamento, calidad contractual y cargo o función.']);
        }
        if ($profesionId !== null && Profesion::query()->whereKey($profesionId)->whereNotNull('estamento_id')->where('estamento_id', '!=', $estamentoId)->exists()) {
            throw ValidationException::withMessages(['reemplazante_profesion_id' => 'La profesión no corresponde al estamento seleccionado.']);
        }

        $detalle->update($nuevos);
        $tramite->historial()->create([
            'user_id' => $actor->id,
            'action_code' => 'ANTECEDENTES_REEMPLAZANTE_CORREGIDOS',
            'observation' => trim($validados['motivo_correccion']),
            'metadata' => ['correcciones' => $cambios],
            'occurred_at' => now(),
        ]);
    }

    private function valorAuditoria(string $campo, int|string|null $valor): array
    {
        if ($campo === 'reemplazante_cargo_funcion') {
            return ['valor' => $valor];
        }

        $modelo = match ($campo) {
            'reemplazante_estamento_id' => Estamento::class,
            'reemplazante_profesion_id' => Profesion::class,
            'reemplazante_calidad_contractual_id' => CalidadContractual::class,
        };
        $catalogo = $valor === null ? null : $modelo::query()->find($valor);

        return ['id' => $valor, 'codigo' => $catalogo?->codigo, 'nombre' => $catalogo?->nombre];
    }
}
