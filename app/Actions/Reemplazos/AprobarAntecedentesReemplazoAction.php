<?php

namespace App\Actions\Reemplazos;

use App\Actions\Tramites\TransicionarTramite;
use App\Models\Tramite;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AprobarAntecedentesReemplazoAction
{
    public function __construct(
        private readonly GuardarRevisionReemplazoAction $guardarRevision,
        private readonly TransicionarTramite $transicionar,
    ) {}

    public function execute(Tramite $tramite, array $datos, User $actor): Tramite
    {
        return DB::transaction(function () use ($tramite, $datos, $actor): Tramite {
            $revision = $this->guardarRevision->execute($tramite, $datos, $actor);
            $revision->update([
                'revisado_por' => $actor->id,
                'revisado_at' => now(),
            ]);

            return $this->transicionar->execute(
                $tramite,
                'APROBAR_ANTECEDENTES',
                $actor,
                metadata: ['revision_id' => $revision->id],
            );
        });
    }
}
