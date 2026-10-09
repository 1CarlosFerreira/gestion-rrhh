<?php

namespace App\Services\Respaldos;

use App\Models\Persona;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

class TransaccionPeriodosTransitorios
{
    public function ejecutar(array $personaIds, Closure $operacion): mixed
    {
        if (DB::getDriverName() === 'mysql' && DB::transactionLevel() !== 0) {
            throw new LogicException('La validación de períodos debe iniciar una transacción propia antes de cualquier lectura consistente.');
        }

        $ids = collect($personaIds)->map(fn ($id): int => (int) $id)->unique()->sort()->values();

        try {
            return DB::transaction(function () use ($ids, $operacion): mixed {
                foreach ($ids as $id) {
                    Persona::query()->lockForUpdate()->findOrFail($id);
                }

                return $operacion();
            }, 3);
        } catch (QueryException $exception) {
            if (in_array((string) $exception->getCode(), ['40001', '41000'], true)
                || in_array((int) ($exception->errorInfo[1] ?? 0), [1205, 1213], true)) {
                throw ValidationException::withMessages(['periodo' => 'La operación encontró una transacción concurrente; reintente.']);
            }

            throw $exception;
        }
    }
}
