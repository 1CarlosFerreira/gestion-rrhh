<?php

namespace App\Actions\Personas;

use App\Models\Persona;
use Illuminate\Support\Facades\DB;

class ActualizarIdentidadPersona
{
    public function execute(Persona $persona, array $datos): Persona
    {
        return DB::transaction(function () use ($persona, $datos): Persona {
            $persona = Persona::query()->lockForUpdate()->findOrFail($persona->id);
            $persona->update($datos);

            $user = $persona->user()->lockForUpdate()->first();
            $user?->update([
                'name' => $persona->nombre_completo,
                'rut' => $persona->rut,
            ]);

            return $persona->refresh();
        });
    }
}
