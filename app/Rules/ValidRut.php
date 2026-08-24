<?php

namespace App\Rules;

use App\Support\Rut\Rut;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidRut implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! Rut::validate($value)) {
            $fail('El campo :attribute debe ser un RUT chileno válido.');
        }
    }
}
