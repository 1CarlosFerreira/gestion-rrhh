<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateGradoEusReemplazoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('reemplazos.revisar_personal');
    }

    public function rules(): array
    {
        return ['grado_eus_informado' => ['required', 'integer', 'min:1']];
    }

    public function messages(): array
    {
        return ['grado_eus_informado.required' => 'Ingrese el último grado E.U.S. informado por Gestión de Personas.'];
    }
}
