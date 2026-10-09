<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegistrarRespaldoTransitorioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->active === true && $this->user()->can('reemplazos.crear');
    }

    public function rules(): array
    {
        return [
            'motivo' => ['required', 'string', 'max:2000'],
            'fecha_desde' => ['required', 'date_format:Y-m-d'],
            'fecha_hasta' => ['required', 'date_format:Y-m-d', 'after_or_equal:fecha_desde'],
            'referencia_externa' => ['nullable', 'string', 'max:200'],
        ];
    }
}
