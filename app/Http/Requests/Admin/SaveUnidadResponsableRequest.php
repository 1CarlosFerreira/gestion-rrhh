<?php

namespace App\Http\Requests\Admin;

use App\Enums\TipoResponsabilidad;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveUnidadResponsableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('responsabilidades.gestionar') === true;
    }

    public function rules(): array
    {
        return [
            'unidad_organizacional_id' => ['required', Rule::exists('unidades_organizacionales', 'id')->where('activo', true)],
            'persona_id' => ['required', 'exists:personas,id'],
            'tipo' => ['required', Rule::enum(TipoResponsabilidad::class)],
            'vigente_desde' => ['required', 'date'],
            'vigente_hasta' => ['nullable', 'date', 'after_or_equal:vigente_desde'],
            'puede_aprobar' => ['boolean'],
            'observacion' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
