<?php

namespace App\Actions\Reemplazos;

use App\Actions\Tramites\TransicionarTramite;
use App\Models\Tramite;
use App\Models\User;
use App\Services\Reemplazos\CoberturaAusenciaService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class EnviarReemplazo
{
    public function __construct(
        private readonly TransicionarTramite $transicionar,
        private readonly CoberturaAusenciaService $coberturas,
        private readonly ReemplazoTransitionGuard $guard,
    ) {}

    public function execute(Tramite $tramite, User $user): Tramite
    {
        if (! $user->can('reemplazos.crear') || Gate::forUser($user)->denies('view', $tramite)) {
            throw new AuthorizationException;
        }

        return DB::transaction(function () use ($tramite, $user): Tramite {
            $detail = $tramite->reemplazo()->with(['ausencia', 'funcionario', 'funcionarioVinculo.unidad', 'funcionarioVinculo.estamento', 'funcionarioVinculo.profesion', 'reemplazante', 'estamento', 'profesion'])->lockForUpdate()->firstOrFail();
            $detail->ausencia()->lockForUpdate()->firstOrFail();
            if ($errors = $this->guard->sendErrors($tramite)) {
                throw ValidationException::withMessages($errors);
            }
            if ($this->coberturas->conflictingCoverage($detail, true)) {
                throw ValidationException::withMessages(['fecha_inicio' => 'El periodo seleccionado se superpone con otra cobertura del mismo funcionario.']);
            }
            $detail->update([
                'funcionario_snapshot' => $detail->funcionario ? [
                    'persona_id' => $detail->funcionario->id, 'rut' => $detail->funcionario->rut, 'nombre_completo' => $detail->funcionario->nombre_completo,
                    'unidad' => $detail->funcionarioVinculo?->unidad?->nombre, 'estamento' => $detail->funcionarioVinculo?->estamento?->nombre,
                    'profesion' => $detail->funcionarioVinculo?->profesion?->nombre, 'cargo' => $detail->funcionarioVinculo?->cargo_texto,
                ] : null,
                'reemplazante_snapshot' => $detail->reemplazante ? [
                    'persona_id' => $detail->reemplazante->id, 'rut' => $detail->reemplazante->rut, 'nombre_completo' => $detail->reemplazante->nombre_completo,
                    'estamento' => $detail->estamento?->nombre, 'profesion' => $detail->profesion?->nombre, 'cargo' => $detail->cargo_texto,
                    'unidad' => $tramite->unidadServicio()->value('nombre'), 'fecha_inicio' => $detail->fecha_inicio?->toDateString(), 'fecha_termino' => $detail->fecha_termino?->toDateString(),
                ] : null,
            ]);

            return $this->transicionar->execute($tramite, 'ENVIAR_A_GESTION_PERSONAS', $user);
        });
    }
}
