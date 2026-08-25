<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveRevisionReemplazoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('reemplazos.revisar_personal');
    }

    public function rules(): array
    {
        return [
            'grado_eus_id' => ['nullable', Rule::exists('grados_eus', 'id')->where('activo', true)],
            'clasificacion_area_id' => ['nullable', Rule::exists('clasificaciones_area', 'id')->where('activo', true)],
            'cumple_normativa' => ['nullable', 'boolean'],
        ];
    }
}
