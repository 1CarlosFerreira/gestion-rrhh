<?php

namespace App\Actions\Tramites;

use App\Models\Tramite;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class ConsultarTramites
{
    public function execute(User $user, array $filters = []): Builder
    {
        return Tramite::query()->visiblePara($user)
            ->with(['tipoTramite', 'unidadServicio', 'estadoTramite', 'creador'])
            ->when($filters['tipo_tramite_id'] ?? null, fn (Builder $query, $value) => $query->where('tipo_tramite_id', $value))
            ->when($filters['estado_tramite_id'] ?? null, function (Builder $query, $value) use ($filters): void {
                $query->where('estado_tramite_id', $value);
                if ($filters['tipo_tramite_id'] ?? null) {
                    $query->whereHas('estadoTramite', fn (Builder $state) => $state->where('tipo_tramite_id', $filters['tipo_tramite_id']));
                }
            })
            ->when($filters['unidad_servicio_id'] ?? null, fn (Builder $query, $value) => $query->where('unidad_servicio_id', $value))
            ->when($filters['codigo'] ?? null, fn (Builder $query, $value) => $query->where('codigo', 'like', '%'.trim($value).'%'))
            ->when($filters['desde'] ?? null, fn (Builder $query, $value) => $query->whereDate('created_at', '>=', $value))
            ->when($filters['hasta'] ?? null, fn (Builder $query, $value) => $query->whereDate('created_at', '<=', $value))
            ->latest();
    }
}
