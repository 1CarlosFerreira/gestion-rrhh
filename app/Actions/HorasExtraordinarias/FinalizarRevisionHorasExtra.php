<?php

namespace App\Actions\HorasExtraordinarias;

use App\Actions\Tramites\TransicionarTramite;
use App\Models\Tramite;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

class FinalizarRevisionHorasExtra
{
    public function __construct(private readonly TransicionarTramite $transicionar) {}

    public function execute(Tramite $tramite, User $user): Tramite
    {
        if (! $user->can('horas_extra.revisar_planilla') || Gate::forUser($user)->denies('view', $tramite)) {
            throw new AuthorizationException;
        }
        $hasObserved = $tramite->horasExtra()->firstOrFail()->funcionarios()->where('review_status', 'OBSERVADA')->exists();

        return $this->transicionar->execute($tramite, $hasObserved ? 'FINALIZAR_REVISION_OBSERVADA' : 'FINALIZAR_REVISION_CONFORME', $user);
    }
}
