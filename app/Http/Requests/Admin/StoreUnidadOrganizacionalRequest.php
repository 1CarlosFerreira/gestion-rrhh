<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUnidadOrganizacionalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('estructura_organizacional.gestionar') === true;
    }

    public function rules(): array
    {
        return [
            'parent_id' => ['nullable', Rule::exists('unidades_organizacionales', 'id')->where('activo', true)],
            'tipo_unidad_organizacional_id' => ['required', Rule::exists('tipos_unidad_organizacional', 'id')->where('activo', true)],
            'codigo' => ['required', 'string', 'max:80', 'unique:unidades_organizacionales,codigo'],
            'nombre' => ['required', 'string', 'max:190'],
            'sigla' => ['nullable', 'string', 'max:50'],
            'descripcion' => ['nullable', 'string'],
            'activo' => ['boolean'],
            'participa_en_aprobacion' => ['boolean'],
            'orden' => ['required', 'integer', 'min:0', 'max:65535'],
        ];
    }
}
