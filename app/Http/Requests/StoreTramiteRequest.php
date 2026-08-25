<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTramiteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('tramites.crear');
    }

    public function rules(): array
    {
        return [
            'tipo_tramite_id' => ['required', 'integer', Rule::exists('tipos_tramite', 'id')->where('activo', true)->whereNotIn('codigo', ['REEMPLAZO', 'HORAS_EXTRAORDINARIAS'])],
            'unidad_servicio_id' => ['required', 'integer', Rule::exists('unidades_servicios', 'id')->where('activo', true)],
        ];
    }
}
