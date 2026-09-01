<?php

namespace App\Actions\Reemplazos;

use App\Models\Tramite;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class GuardarRevisionReemplazo
{
    public function execute(Tramite $tramite, array $data, User $user): bool
    {
        if (! $user->can('reemplazos.revisar_personal') || Gate::forUser($user)->denies('view', $tramite) || $tramite->estadoTramite->codigo !== 'EN_REVISION') {
            throw new AuthorizationException;
        }

        return DB::transaction(function () use ($tramite, $data, $user): bool {
            $locked = Tramite::query()->lockForUpdate()->findOrFail($tramite->id);
            $values = collect($data)->only(['grado_eus_id', 'clasificacion_area_id', 'cumple_normativa'])->filter(fn ($value) => $value !== null)->all();
            $revision = $locked->revisionReemplazo()->first();

            if ($values === [] || ($revision && ! $this->hasChanges($revision, $values))) {
                return false;
            }

            $locked->revisionReemplazo()->updateOrCreate([], $values);
            $locked->historial()->create(['user_id' => $user->id, 'action_code' => 'REEMPLAZO_REVISION_ACTUALIZADA', 'occurred_at' => now()]);

            return true;
        });
    }

    private function hasChanges(object $revision, array $values): bool
    {
        foreach ($values as $field => $value) {
            if ($field === 'cumple_normativa') {
                if ($revision->{$field} === null || (bool) $revision->{$field} !== (bool) $value) {
                    return true;
                }

                continue;
            }

            if ((int) $revision->{$field} !== (int) $value) {
                return true;
            }
        }

        return false;
    }
}
