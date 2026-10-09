<?php

namespace App\Services\Respaldos;

use DateTimeImmutable;
use Illuminate\Validation\ValidationException;

class PeriodosInclusivos
{
    public function validar(string $desde, string $hasta): void
    {
        $inicio = DateTimeImmutable::createFromFormat('!Y-m-d', $desde);
        $fin = DateTimeImmutable::createFromFormat('!Y-m-d', $hasta);

        if ($inicio === false || $fin === false || $inicio->format('Y-m-d') !== $desde || $fin->format('Y-m-d') !== $hasta || $inicio > $fin) {
            throw ValidationException::withMessages(['fecha_desde' => 'El período debe tener fechas calendario válidas y ordenadas.']);
        }
    }

    public function contiene(string $exteriorDesde, string $exteriorHasta, string $interiorDesde, string $interiorHasta): bool
    {
        return $exteriorDesde <= $interiorDesde && $interiorHasta <= $exteriorHasta;
    }
}
