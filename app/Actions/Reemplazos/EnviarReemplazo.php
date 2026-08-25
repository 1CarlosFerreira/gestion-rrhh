<?php

namespace App\Actions\Reemplazos;

use App\Actions\Tramites\TransicionarTramite;
use App\Models\Tramite;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class EnviarReemplazo
{
    public function __construct(private readonly TransicionarTramite $transicionar) {}

    public function execute(Tramite $tramite, User $user): Tramite
    {
        if (! $user->can('reemplazos.crear') || Gate::forUser($user)->denies('view', $tramite)) {
            throw new AuthorizationException;
        }

        return DB::transaction(function () use ($tramite, $user): Tramite {
            $detail = $tramite->reemplazo()->with(['funcionario', 'funcionarioVinculo.unidad', 'funcionarioVinculo.estamento', 'funcionarioVinculo.profesion', 'reemplazante', 'estamento', 'profesion'])->firstOrFail();
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
