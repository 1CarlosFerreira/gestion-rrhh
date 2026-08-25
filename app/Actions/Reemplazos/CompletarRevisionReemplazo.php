<?php

namespace App\Actions\Reemplazos;

use App\Actions\Tramites\TransicionarTramite;
use App\Models\Tramite;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class CompletarRevisionReemplazo
{
    public function __construct(private readonly TransicionarTramite $transicionar) {}

    public function execute(Tramite $tramite, User $user): Tramite
    {
        if (! $user->can('reemplazos.revisar_personal') || Gate::forUser($user)->denies('view', $tramite)) {
            throw new AuthorizationException;
        }

        return DB::transaction(function () use ($tramite, $user): Tramite {
            $revision = $tramite->revisionReemplazo()->first();
            if ($revision) {
                $revision->update(['completed_by' => $user->id, 'completed_at' => now()]);
            }

            return $this->transicionar->execute($tramite, 'COMPLETAR_REVISION', $user);
        });
    }
}
