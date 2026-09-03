<?php

namespace App\Services;

use App\Models\UnidadOrganizacional;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class EstructuraOrganizacionalService
{
    public function validarMovimiento(UnidadOrganizacional $unidad, ?UnidadOrganizacional $nuevoPadre): void
    {
        if ($nuevoPadre === null) {
            return;
        }

        if ($unidad->is($nuevoPadre) || $this->contiene($unidad, $nuevoPadre)) {
            throw ValidationException::withMessages(['parent_id' => 'La unidad superior no puede ser la propia unidad ni uno de sus descendientes.']);
        }
    }

    public function ancestros(UnidadOrganizacional $unidad): Collection
    {
        $ancestros = collect();
        $actual = $unidad->parent;
        $visitados = [$unidad->id => true];

        while ($actual !== null) {
            if (isset($visitados[$actual->id])) {
                throw new \LogicException('Se detectó un ciclo en la estructura organizacional.');
            }
            $visitados[$actual->id] = true;
            $ancestros->prepend($actual);
            $actual = $actual->parent;
        }

        return $ancestros;
    }

    public function descendientes(UnidadOrganizacional $unidad): Collection
    {
        $resultado = collect();
        $pendientes = $unidad->children()->get()->all();
        $visitados = [$unidad->id => true];

        while ($actual = array_shift($pendientes)) {
            if (isset($visitados[$actual->id])) {
                throw new \LogicException('Se detectó un ciclo en la estructura organizacional.');
            }
            $visitados[$actual->id] = true;
            $resultado->push($actual);
            array_push($pendientes, ...$actual->children()->get()->all());
        }

        return $resultado;
    }

    public function contiene(UnidadOrganizacional $unidad, UnidadOrganizacional $posibleDescendiente): bool
    {
        return $this->descendientes($unidad)->contains->is($posibleDescendiente);
    }

    public function ruta(UnidadOrganizacional $unidad): string
    {
        return $this->ancestros($unidad)->push($unidad)->pluck('nombre')->implode(' / ');
    }

    public function nivel(UnidadOrganizacional $unidad): int
    {
        return $this->ancestros($unidad)->count();
    }
}
