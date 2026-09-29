<?php

namespace App\Actions\Tramites;

use App\Models\Tramite;
use App\Support\Rut\Rut;
use Illuminate\Database\Eloquent\Builder;

class ConsultarTramites
{
    public function execute(array $filters = []): Builder
    {
        $search = trim((string) ($filters['buscar'] ?? ''));

        return Tramite::query()
            ->with([
                'tipoTramite:id,codigo,nombre',
                'estadoTramite:id,tipo_tramite_id,codigo,nombre',
                'unidadOrganizacional:id,nombre,sigla',
                'creador:id,name',
                'reemplazo:id,tramite_id,funcionario_id,reemplazante_id',
                'reemplazo.funcionario:id,nombres,apellido_paterno,apellido_materno',
                'reemplazo.reemplazante:id,nombres,apellido_paterno,apellido_materno',
            ])
            ->when($search !== '', fn (Builder $query) => $this->applySearch($query, $search))
            ->when($filters['tipo'] ?? null, fn (Builder $query, $value) => $query->where('tipo_tramite_id', $value))
            ->when($filters['estado'] ?? null, fn (Builder $query, $value) => $query->where('estado_tramite_id', $value))
            ->when($filters['unidad'] ?? null, fn (Builder $query, $value) => $query->where('unidad_organizacional_id', $value))
            ->when($filters['creador'] ?? null, fn (Builder $query, $value) => $query->where('created_by', $value))
            ->when($filters['fecha_desde'] ?? null, fn (Builder $query, $value) => $query->where('created_at', '>=', $value.' 00:00:00'))
            ->when($filters['fecha_hasta'] ?? null, fn (Builder $query, $value) => $query->where('created_at', '<=', $value.' 23:59:59'))
            ->when(
                ($filters['orden'] ?? 'recientes') === 'antiguos',
                fn (Builder $query) => $query->orderBy('created_at')->orderBy('id'),
                fn (Builder $query) => $query->orderByDesc('created_at')->orderByDesc('id'),
            );
    }

    private function applySearch(Builder $query, string $search): void
    {
        $escapedSearch = $this->escapeLike($search);
        $normalizedRut = $this->escapeLike(Rut::normalize($search));

        $query->where(function (Builder $matches) use ($search, $escapedSearch, $normalizedRut): void {
            $matches
                ->where('codigo', $search)
                ->orWhereRaw("codigo LIKE ? ESCAPE '!'", [$escapedSearch.'%'])
                ->orWhereHas('reemplazo.funcionario', fn (Builder $person) => $this->applyPersonSearch($person, $escapedSearch, $normalizedRut))
                ->orWhereHas('reemplazo.reemplazante', fn (Builder $person) => $this->applyPersonSearch($person, $escapedSearch, $normalizedRut));
        });
    }

    private function applyPersonSearch(Builder $query, string $search, string $normalizedRut): void
    {
        $query->where(function (Builder $person) use ($search, $normalizedRut): void {
            $person
                ->whereRaw("rut LIKE ? ESCAPE '!'", [$normalizedRut.'%'])
                ->orWhereRaw("nombres LIKE ? ESCAPE '!'", ['%'.$search.'%'])
                ->orWhereRaw("apellido_paterno LIKE ? ESCAPE '!'", ['%'.$search.'%'])
                ->orWhereRaw("apellido_materno LIKE ? ESCAPE '!'", ['%'.$search.'%']);
        });
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value);
    }
}
