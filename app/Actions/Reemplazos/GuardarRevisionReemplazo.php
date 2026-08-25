<?php

namespace App\Actions\Reemplazos;

use App\Models\Tramite;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class GuardarRevisionReemplazo
{
    public function execute(Tramite $tramite, array $data, User $user): void
    {
        if (! $user->can('reemplazos.revisar_personal') || Gate::forUser($user)->denies('view', $tramite) || $tramite->estadoTramite->codigo !== 'EN_REVISION') {
            throw new AuthorizationException;
        }
        DB::transaction(function () use ($tramite, $data, $user): void {
            $tramite->revisionReemplazo()->updateOrCreate([], collect($data)->only(['grado_eus_id', 'clasificacion_area_id', 'cumple_normativa'])->all());
            $tramite->historial()->create(['user_id' => $user->id, 'action_code' => 'REEMPLAZO_REVISION_ACTUALIZADA', 'occurred_at' => now()]);
        });
    }
}
