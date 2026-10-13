<?php

namespace App\Services\Recuperacion;

use App\Support\Rut\Rut;
use Illuminate\Support\Facades\DB;

final class PlanRecuperacionPersonas
{
    public function build(array $dump, array $unitMap = []): array
    {
        $rows = $dump['rows'];
        $people = [];
        $links = [];
        $conflicts = [];
        $sourcePeople = [];
        $sourceRutCounts = collect($rows['personas'])->countBy(fn ($row) => Rut::normalize($row['rut']));
        $currentPeopleByRut = DB::table('personas')->get()->groupBy(fn ($row) => Rut::normalize($row->rut));
        $currentLinks = DB::table('persona_unidad_vinculos')->get();
        $units = DB::table('unidades_organizacionales')->get()->keyBy('codigo');
        $catalogs = [];

        foreach (['estamentos', 'profesiones', 'calidades_contractuales'] as $table) {
            $catalogs[$table] = DB::table($table)->get()->keyBy('codigo');
        }

        foreach ($rows['personas'] as $source) {
            $id = (int) $source['id'];
            $rut = Rut::normalize($source['rut']);
            if (array_key_exists($id, $sourcePeople) || $sourceRutCounts[$rut] > 1 || ! Rut::validate($rut) || trim((string) $source['nombres']) === '') {
                $conflicts[] = $this->conflict('persona', $id, 'identidad_invalida_o_duplicada');
                $sourcePeople[$id] = null;

                continue;
            }
            $sourcePeople[$id] = $rut;
            $matches = $currentPeopleByRut->get($rut, collect());
            if ($matches->count() > 1) {
                $conflicts[] = $this->conflict('persona', $id, 'rut_actual_duplicado_normalizado');
                $sourcePeople[$id] = null;

                continue;
            }
            $existing = $matches->first();
            if ($existing) {
                if ($this->samePerson($source, $existing)) {
                    $people[] = ['action' => 'conservar', 'source_id' => $id, 'rut' => $rut, 'current_id' => $existing->id];
                } else {
                    $conflicts[] = $this->conflict('persona', $id, 'identidad_existente_difiere');
                    $sourcePeople[$id] = null;
                }
            } else {
                $people[] = ['action' => 'crear', 'source_id' => $id, 'rut' => $rut, 'data' => [
                    'rut' => $rut,
                    'nombres' => trim($source['nombres']),
                    'apellido_paterno' => $source['apellido_paterno'] === null ? null : trim($source['apellido_paterno']),
                    'apellido_materno' => $source['apellido_materno'] === null ? null : trim($source['apellido_materno']),
                    'active' => (int) $source['active'],
                ]];
            }
        }

        $sourceCatalogs = [];
        foreach (['estamentos', 'profesiones', 'calidades_contractuales'] as $table) {
            $sourceCatalogs[$table] = collect($rows[$table])->keyBy('id');
        }

        $personActions = collect($people)->keyBy('source_id');
        $plannedLinks = [];
        $seenLinkIds = [];
        foreach ($rows['persona_unidad_vinculos'] as $source) {
            $id = (int) $source['id'];
            if (isset($seenLinkIds[$id])) {
                $conflicts[] = $this->conflict('vinculo', $id, 'id_duplicado');

                continue;
            }
            $seenLinkIds[$id] = true;
            $person = $personActions->get((int) $source['persona_id']);
            if (! $person || ! isset($sourcePeople[(int) $source['persona_id']])) {
                $conflicts[] = $this->conflict('vinculo', $id, 'persona_no_resuelta');

                continue;
            }
            $mapping = $unitMap[(string) $source['unidad_organizacional_id']] ?? null;
            if (! is_array($mapping)
                || ! is_string($mapping['codigo'] ?? null)
                || ! is_string($mapping['nombre'] ?? null)
                || ! is_string($mapping['evidencia'] ?? null)
                || trim($mapping['evidencia']) === '') {
                $conflicts[] = $this->conflict('vinculo', $id, 'unidad_sin_correspondencia_verificada', (int) $source['unidad_organizacional_id']);

                continue;
            }
            $unit = $units->get($mapping['codigo']);
            if (! $unit || $unit->nombre !== $mapping['nombre']) {
                $conflicts[] = $this->conflict('vinculo', $id, 'unidad_destino_no_coincide', (int) $source['unidad_organizacional_id']);

                continue;
            }
            $resolved = [];
            foreach (['estamentos' => 'estamento_id', 'profesiones' => 'profesion_id', 'calidades_contractuales' => 'calidad_contractual_id'] as $table => $field) {
                if ($source[$field] === null && $table === 'profesiones') {
                    $resolved[$field] = null;

                    continue;
                }
                $sourceCatalog = $sourceCatalogs[$table]->get((string) $source[$field]);
                $target = $sourceCatalog ? $catalogs[$table]->get($sourceCatalog['codigo']) : null;
                if (! $target || $target->nombre !== $sourceCatalog['nombre']) {
                    $conflicts[] = $this->conflict('vinculo', $id, 'catalogo_no_coincide');

                    continue 2;
                }
                $resolved[$field] = $target->id;
                if ($table === 'profesiones' && $target->estamento_id !== null && $target->estamento_id != $resolved['estamento_id']) {
                    $conflicts[] = $this->conflict('vinculo', $id, 'profesion_incompatible');

                    continue 2;
                }
            }
            if (! $this->validLink($source)) {
                $conflicts[] = $this->conflict('vinculo', $id, 'datos_laborales_invalidos_o_origen_no_recuperable');

                continue;
            }
            $cargo = trim(preg_replace('/\s+/u', ' ', $source['cargo_funcion']) ?? '');
            $candidate = [
                'source_id' => $id,
                'source_person_id' => (int) $source['persona_id'],
                'current_person_id' => $person['current_id'] ?? null,
                'unidad_organizacional_id' => $unit->id,
                ...$resolved,
                'cargo_funcion' => $cargo,
                'cargo_funcion_normalizado' => mb_strtolower($cargo),
                'grado_eus' => $source['grado_eus'] === null ? null : (int) $source['grado_eus'],
                'vigente_desde' => $source['vigente_desde'],
                'vigente_hasta' => $source['vigente_hasta'],
            ];
            $matching = $currentLinks->filter(fn ($link) => $candidate['current_person_id'] !== null
                && $link->persona_id == $candidate['current_person_id']
                && $link->unidad_organizacional_id == $unit->id
                && $link->calidad_contractual_id == $resolved['calidad_contractual_id']
                && $link->cargo_funcion_normalizado === $candidate['cargo_funcion_normalizado']);
            $overlap = $matching->first(fn ($link) => $this->overlap($candidate['vigente_desde'], $candidate['vigente_hasta'], $link->vigente_desde, $link->vigente_hasta));
            if ($overlap) {
                if ($this->sameLink($candidate, $overlap)) {
                    $links[] = ['action' => 'conservar', ...$candidate];
                } else {
                    $conflicts[] = $this->conflict('vinculo', $id, 'vinculo_equivalente_solapado');
                }

                continue;
            }
            $duplicate = collect($plannedLinks)->first(fn ($other) => $other['source_person_id'] === $candidate['source_person_id']
                && $other['unidad_organizacional_id'] == $candidate['unidad_organizacional_id']
                && $other['calidad_contractual_id'] == $candidate['calidad_contractual_id']
                && $other['cargo_funcion_normalizado'] === $candidate['cargo_funcion_normalizado']
                && $this->overlap($candidate['vigente_desde'], $candidate['vigente_hasta'], $other['vigente_desde'], $other['vigente_hasta']));
            if ($duplicate) {
                $conflicts[] = $this->conflict('vinculo', $id, 'duplicado_en_respaldo');

                continue;
            }
            $plannedLinks[] = $candidate;
            $links[] = ['action' => 'crear', ...$candidate];
        }

        return ['sha256' => $dump['sha256'], 'source' => ['personas' => count($rows['personas']), 'vinculos' => count($rows['persona_unidad_vinculos'])], 'personas' => $people, 'vinculos' => $links, 'conflictos' => $conflicts];
    }

    private function samePerson(array $source, object $existing): bool
    {
        foreach (['nombres', 'apellido_paterno', 'apellido_materno'] as $field) {
            if (trim((string) $source[$field]) !== trim((string) $existing->$field)) {
                return false;
            }
        }

        return (int) $source['active'] === (int) $existing->active;
    }

    private function validLink(array $source): bool
    {
        $cargo = trim(preg_replace('/\s+/u', ' ', (string) $source['cargo_funcion']) ?? '');

        return in_array($source['origen'], ['MANUAL', 'IMPORTACION'], true)
            && $source['origen_tramite_id'] === null
            && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $source['vigente_desde']) === 1
            && ($source['vigente_hasta'] === null || $source['vigente_hasta'] >= $source['vigente_desde'])
            && ($source['vigente_hasta'] === null || preg_match('/^\d{4}-\d{2}-\d{2}$/', $source['vigente_hasta']) === 1)
            && $cargo !== ''
            && mb_strlen($source['cargo_funcion']) <= 200
            && $source['cargo_funcion_normalizado'] === mb_strtolower($cargo)
            && ($source['grado_eus'] === null || ((int) $source['grado_eus'] >= 1 && (int) $source['grado_eus'] <= 99));
    }

    private function sameLink(array $candidate, object $existing): bool
    {
        foreach (['estamento_id', 'profesion_id', 'grado_eus', 'vigente_desde', 'vigente_hasta'] as $field) {
            if ((string) ($candidate[$field] ?? '') !== (string) ($existing->$field ?? '')) {
                return false;
            }
        }

        return trim($existing->cargo_funcion) === $candidate['cargo_funcion']
            && in_array($existing->origen, ['MANUAL', 'IMPORTACION'], true)
            && $existing->origen_tramite_id === null;
    }

    private function overlap(string $startA, ?string $endA, string $startB, ?string $endB): bool
    {
        return $startA <= ($endB ?? '9999-12-31') && $startB <= ($endA ?? '9999-12-31');
    }

    private function conflict(string $type, int $id, string $reason, ?int $sourceUnitId = null): array
    {
        return ['tipo' => $type, 'origen_id' => $id, 'motivo' => $reason, ...($sourceUnitId === null ? [] : ['unidad_origen_id' => $sourceUnitId])];
    }
}
