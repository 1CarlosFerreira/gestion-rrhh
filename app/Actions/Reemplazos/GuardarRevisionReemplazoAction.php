<?php

namespace App\Actions\Reemplazos;

use App\Models\ReemplazoRevision;
use App\Models\Tramite;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class GuardarRevisionReemplazoAction
{
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

            return $tramite->revisionReemplazo()->updateOrCreate([], $this->validarDatos($datos));
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
}
