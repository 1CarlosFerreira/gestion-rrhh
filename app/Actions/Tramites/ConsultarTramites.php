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
            ->with(['tipoTramite', 'unidadServicio', 'estadoTramite', 'creador', 'reemplazo.funcionario', 'horasExtra.funcionarios'])
            ->when($filters['tipo_tramite_id'] ?? null, fn (Builder $query, $value) => $query->where('tipo_tramite_id', $value))
            ->when($filters['estado_tramite_id'] ?? null, function (Builder $query, $value) use ($filters): void {
                $query->where('estado_tramite_id', $value);
                if ($filters['tipo_tramite_id'] ?? null) {
                    $query->whereHas('estadoTramite', fn (Builder $state) => $state->where('tipo_tramite_id', $filters['tipo_tramite_id']));
                }
            })
            ->when($filters['unidad_servicio_id'] ?? null, fn (Builder $query, $value) => $query->where('unidad_servicio_id', $value))
            ->when($filters['codigo'] ?? null, function (Builder $query, $value): void {
                $term = trim($value);
                $query->where(function (Builder $match) use ($term): void {
                    $match->where('codigo', 'like', '%'.$term.'%')
                        ->orWhereHas('reemplazo.funcionario', fn (Builder $person) => $person->buscar($term))
                        ->orWhereHas('reemplazo.reemplazante', fn (Builder $person) => $person->buscar($term))
                        ->orWhereHas('horasExtra.funcionarios.persona', fn (Builder $person) => $person->buscar($term));
                });
            })
            ->when($filters['estado_grupo'] ?? null, function (Builder $query, $group): void {
                match ($group) {
                    'borradores' => $query->whereHas('estadoTramite', fn (Builder $state) => $state->where('codigo', 'BORRADOR')),
                    'en_revision' => $query->whereHas('estadoTramite', fn (Builder $state) => $state->whereIn('codigo', ['ENVIADA_GESTION_PERSONAS', 'EN_REVISION', 'PLANILLA_DISPONIBLE', 'EN_REVISION_JEFATURA'])),
                    'devueltos' => $query->whereHas('estadoTramite', fn (Builder $state) => $state->where('codigo', 'DEVUELTA_CORRECCION')),
                    'nuevas_por_revisar' => $query->whereHas('estadoTramite', fn (Builder $state) => $state->where('codigo', 'ENVIADA_GESTION_PERSONAS')),
                    'en_revision_gp' => $query->whereHas('estadoTramite', fn (Builder $state) => $state->where('codigo', 'EN_REVISION')),
                    'devueltas_esperando_correccion' => $query->whereHas('estadoTramite', fn (Builder $state) => $state->where('codigo', 'DEVUELTA_CORRECCION')),
                    'listas_generar_documento' => $query->whereHas('estadoTramite', fn (Builder $state) => $state->where('codigo', 'LISTA_GENERAR_DOCUMENTO')),
                    'documento_generado' => $query->whereHas('estadoTramite', fn (Builder $state) => $state->where('codigo', 'DOCUMENTO_GENERADO')),
                    'formalizados' => $query->whereNotNull('finalized_at'),
                    default => null,
                };
            })
            ->when($filters['desde'] ?? null, fn (Builder $query, $value) => $query->whereDate('created_at', '>=', $value))
            ->when($filters['hasta'] ?? null, fn (Builder $query, $value) => $query->whereDate('created_at', '<=', $value))
            ->latest();
    }
}
