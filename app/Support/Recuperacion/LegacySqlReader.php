<?php

namespace App\Support\Recuperacion;

use RuntimeException;

final class LegacySqlReader
{
    private const COLUMNS = [
        'personas' => ['id', 'rut', 'nombres', 'apellido_paterno', 'apellido_materno', 'active', 'created_at', 'updated_at'],
        'persona_unidad_vinculos' => ['id', 'persona_id', 'unidad_organizacional_id', 'estamento_id', 'profesion_id', 'calidad_contractual_id', 'cargo_funcion', 'cargo_funcion_normalizado', 'grado_eus', 'vigente_desde', 'vigente_hasta', 'origen', 'origen_tramite_id', 'observacion', 'created_by', 'updated_by', 'created_at', 'updated_at'],
        'estamentos' => ['id', 'codigo', 'nombre', 'activo', 'created_at', 'updated_at'],
        'profesiones' => ['id', 'estamento_id', 'codigo', 'nombre', 'activo', 'created_at', 'updated_at'],
        'calidades_contractuales' => ['id', 'codigo', 'nombre', 'descripcion', 'activo', 'orden', 'created_at', 'updated_at'],
        'unidades_organizacionales' => ['id', 'parent_id', 'tipo_unidad_organizacional_id', 'codigo', 'nombre', 'sigla', 'descripcion', 'activo', 'participa_en_aprobacion', 'orden', 'created_at', 'updated_at'],
    ];

    public function read(string $path): array
    {
        if (! is_file($path) || ! is_readable($path) || filesize($path) > 20 * 1024 * 1024) {
            throw new RuntimeException('El respaldo no existe, no se puede leer o supera 20 MiB.');
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException('No se pudo abrir el respaldo.');
        }

        $result = array_fill_keys(array_keys(self::COLUMNS), []);
        $tables = [];
        $columns = [];
        $currentTable = null;
        try {
            while (($line = fgets($handle)) !== false) {
                if (preg_match('/^CREATE TABLE `([a-z_]+)` /', $line, $match)) {
                    $tables[$match[1]] = true;
                    $currentTable = isset(self::COLUMNS[$match[1]]) ? $match[1] : null;
                    if ($currentTable !== null) {
                        $columns[$currentTable] = [];
                    }
                } elseif ($currentTable !== null && preg_match('/^\s+`([a-z_]+)` /', $line, $match)) {
                    $columns[$currentTable][] = $match[1];
                } elseif ($currentTable !== null && preg_match('/^\) ENGINE=/', $line)) {
                    if ($columns[$currentTable] !== self::COLUMNS[$currentTable]) {
                        throw new RuntimeException("Columnas inesperadas en tabla {$currentTable}.");
                    }
                    $currentTable = null;
                }
                if (! str_starts_with($line, 'INSERT INTO ')) {
                    continue;
                }
                if (! preg_match('/^INSERT INTO `([a-z_]+)` /', $line, $tableMatch)) {
                    throw new RuntimeException('Formato INSERT no admitido.');
                }
                if (! isset(self::COLUMNS[$tableMatch[1]])) {
                    continue;
                }
                if (! preg_match('/^INSERT INTO `([a-z_]+)` VALUES (.*);\s*$/s', $line, $match)) {
                    throw new RuntimeException('Formato INSERT no admitido.');
                }
                $table = $match[1];
                foreach ($this->parseValues($match[2]) as $values) {
                    if (count($values) !== count(self::COLUMNS[$table])) {
                        throw new RuntimeException("Estructura inesperada en tabla {$table}.");
                    }
                    $result[$table][] = array_combine(self::COLUMNS[$table], $values);
                }
            }
        } finally {
            fclose($handle);
        }

        foreach (['personas', 'persona_unidad_vinculos', 'estamentos', 'profesiones', 'calidades_contractuales'] as $required) {
            if (! isset($tables[$required])) {
                throw new RuntimeException("Falta la tabla esperada {$required} en el respaldo.");
            }
        }

        return ['rows' => $result, 'tables' => array_keys($tables), 'sha256' => hash_file('sha256', $path)];
    }

    private function parseValues(string $sql): array
    {
        $rows = [];
        $length = strlen($sql);
        $position = 0;
        while ($position < $length) {
            $this->skipWhitespaceAndCommas($sql, $position);
            if ($position >= $length) {
                break;
            }
            if ($sql[$position++] !== '(') {
                throw new RuntimeException('Sintaxis INSERT inesperada.');
            }
            $row = [];
            do {
                $this->skipWhitespace($sql, $position);
                if ($position >= $length) {
                    throw new RuntimeException('INSERT incompleto.');
                }
                if ($sql[$position] === "'") {
                    $position++;
                    $value = '';
                    $closed = false;
                    while ($position < $length) {
                        $character = $sql[$position++];
                        if ($character === "'") {
                            if ($position < $length && $sql[$position] === "'") {
                                $value .= "'";
                                $position++;
                            } else {
                                $closed = true;
                                break;
                            }
                        } elseif ($character === '\\') {
                            if ($position >= $length) {
                                throw new RuntimeException('Escape SQL incompleto.');
                            }
                            $escaped = $sql[$position++];
                            $value .= match ($escaped) {
                                '0' => "\0", 'n' => "\n", 'r' => "\r", 't' => "\t", 'b' => "\x08", 'Z' => "\x1a", default => $escaped,
                            };
                        } else {
                            $value .= $character;
                        }
                    }
                    if (! $closed) {
                        throw new RuntimeException('Cadena SQL incompleta.');
                    }
                } else {
                    $start = $position;
                    while ($position < $length && $sql[$position] !== ',' && $sql[$position] !== ')') {
                        $position++;
                    }
                    $raw = trim(substr($sql, $start, $position - $start));
                    if ($raw !== 'NULL' && ! preg_match('/^-?\d+(?:\.\d+)?$/', $raw)) {
                        throw new RuntimeException('Valor SQL no admitido.');
                    }
                    $value = $raw === 'NULL' ? null : $raw;
                }
                $row[] = $value;
                $this->skipWhitespace($sql, $position);
                $separator = $sql[$position++] ?? null;
                if ($separator === ')') {
                    break;
                }
                if ($separator !== ',') {
                    throw new RuntimeException('Separador SQL inesperado.');
                }
            } while (true);
            $rows[] = $row;
        }

        return $rows;
    }

    private function skipWhitespaceAndCommas(string $sql, int &$position): void
    {
        while (isset($sql[$position]) && (ctype_space($sql[$position]) || $sql[$position] === ',')) {
            $position++;
        }
    }

    private function skipWhitespace(string $sql, int &$position): void
    {
        while (isset($sql[$position]) && ctype_space($sql[$position])) {
            $position++;
        }
    }
}
