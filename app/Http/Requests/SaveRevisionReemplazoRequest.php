<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveRevisionReemplazoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('reemplazos.revisar') === true;
    }

    public function rules(): array
    {
        return ['grado_eus' => ['nullable', 'integer', 'min:1', 'max:99'], 'clasificacion_area_id' => ['nullable', Rule::exists('clasificaciones_area', 'id')->where('activo', true)], 'cumple_normativa' => ['nullable', 'boolean'], 'observacion_administrativa' => ['nullable', 'string', 'max:5000']];
    }
}
