<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveHorasExtraRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('horas_extra.crear') ?? false;
    }

    public function rules(): array
    {
        return [
            'unidad_servicio_id' => ['required', 'integer', Rule::exists('unidades_servicios', 'id')->where('activo', true)],
            'year' => ['required', 'integer', 'between:2000,2100'],
            'month' => ['required', 'integer', 'between:1,12'],
            'persona_ids' => ['required', 'array', 'min:1'],
            'persona_ids.*' => ['required', 'integer', 'distinct', Rule::exists('personas', 'id')->where('active', true)],
        ];
    }
}
