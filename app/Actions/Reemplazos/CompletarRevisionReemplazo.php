<?php

namespace App\Actions\Reemplazos;

use App\Actions\Tramites\TransicionarTramite;
use App\Models\Tramite;
use App\Models\User;
use App\Services\Documentos\SnapshotRemitenteReemplazo;
use App\Services\Reemplazos\CoberturaAusenciaService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CompletarRevisionReemplazo
{
    public function __construct(private readonly TransicionarTramite $transicionar, private readonly SnapshotRemitenteReemplazo $snapshot, private readonly CoberturaAusenciaService $coberturas) {}

    public function execute(Tramite $tramite, User $user): Tramite
    {
        if (! $user->can('reemplazos.revisar_personal') || Gate::forUser($user)->denies('view', $tramite)) {
            throw new AuthorizationException;
        }

        return DB::transaction(function () use ($tramite, $user): Tramite {
            $coverage = $tramite->reemplazo()->with('ausencia')->lockForUpdate()->firstOrFail();
            $coverage->ausencia()->lockForUpdate()->firstOrFail();
            if ($errors = $this->coberturas->validationErrors($coverage)) {
                throw ValidationException::withMessages($errors);
            }
            if ($this->coberturas->conflictingCoverage($coverage, true)) {
                throw ValidationException::withMessages(['fecha_inicio' => 'El periodo seleccionado se superpone con otra cobertura del mismo funcionario.']);
            }
            $revision = $tramite->revisionReemplazo()->first();
            if ($revision) {
                $revision->update(['completed_by' => $user->id, 'completed_at' => now()]);
            }
            $tramite->loadMissing(['creador.roles', 'unidadServicio']);
            $tramite->reemplazo()->update(['remitente_snapshot' => $this->snapshot->crear($tramite)]);

            return $this->transicionar->execute(
                $tramite,
                'COMPLETAR_REVISION',
                $user,
                null,
            );
        });
    }
}
