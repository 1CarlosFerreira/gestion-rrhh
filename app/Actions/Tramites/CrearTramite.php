<?php

namespace App\Actions\Tramites;

use App\Models\EstadoTramite;
use App\Models\TipoTramite;
use App\Models\Tramite;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CrearTramite
{
    public function __construct(private readonly GenerarCodigoTramite $generarCodigo) {}

    public function execute(TipoTramite $tipo, User $user): Tramite
    {
        if (! $user->can('tramites.crear')) {
            throw new AuthorizationException('No tiene permiso para crear trámites.');
        }
        if (! $tipo->activo) {
            throw ValidationException::withMessages(['tramite' => 'El tipo de trámite debe estar activo.']);
        }

        return DB::transaction(function () use ($tipo, $user): Tramite {
            $estado = EstadoTramite::query()->where([
                'tipo_tramite_id' => $tipo->id,
                'codigo' => 'BORRADOR',
                'activo' => true,
            ])->first();

            if (! $estado) {
                throw ValidationException::withMessages(['tipo_tramite_id' => 'El tipo de trámite no tiene un estado BORRADOR activo.']);
            }

            $tramite = Tramite::query()->create([
                'public_id' => (string) Str::ulid(),
                'codigo' => $this->generarCodigo->execute(),
                'tipo_tramite_id' => $tipo->id,
                'estado_tramite_id' => $estado->id,
                'created_by' => $user->id,
            ]);

            $tramite->historial()->create([
                'user_id' => $user->id,
                'action_code' => 'TRAMITE_CREADO',
                'to_estado_id' => $estado->id,
                'occurred_at' => now(),
            ]);

            return $tramite;
        });
    }
}
