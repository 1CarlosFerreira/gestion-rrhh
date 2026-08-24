<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserUnidadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('usuarios.unidades.gestionar');
    }

    public function rules(): array
    {
        return [
            'unidad_servicio_id' => ['required', 'integer', Rule::exists('unidades_servicios', 'id')],
            'valid_from' => ['nullable', 'date'],
            'valid_to' => ['nullable', 'date', 'after_or_equal:valid_from'],
        ];
    }
}
