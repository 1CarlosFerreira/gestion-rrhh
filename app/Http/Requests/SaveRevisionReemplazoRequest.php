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
        $v3 = $this->route('tramite')?->solicitudContrato()->exists() === true;
        $correccion = $v3 ? 'nullable' : 'prohibited';

        $reglaRespaldo = $v3
            ? ($this->routeIs('gestion-personas.reemplazos.approve') ? 'required' : 'nullable')
            : 'prohibited';

        return [
            'grado_eus' => ['nullable', 'integer', 'min:1', 'max:99'],
            'clasificacion_area_id' => ['nullable', Rule::exists('clasificaciones_area', 'id')->where('activo', true)],
            'cumple_normativa' => ['nullable', 'boolean'],
            'observacion_administrativa' => ['nullable', 'string', 'max:5000'],
            'respaldo_version_id' => [$reglaRespaldo, 'integer', Rule::exists('respaldo_transitorio_versiones', 'id')],
            'reemplazante_estamento_id' => [$correccion, Rule::exists('estamentos', 'id')->where('activo', true)],
            'reemplazante_profesion_id' => [$correccion, Rule::exists('profesiones', 'id')->where('activo', true)],
            'reemplazante_calidad_contractual_id' => [$correccion, Rule::exists('calidades_contractuales', 'id')->where('activo', true)],
            'reemplazante_cargo_funcion' => [$v3 ? 'nullable' : 'prohibited', 'string', 'max:200'],
            'motivo_correccion' => [$v3 ? 'nullable' : 'prohibited', 'string', 'max:5000'],
        ];
    }
}
