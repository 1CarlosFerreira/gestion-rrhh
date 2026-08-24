<?php

namespace App\Http\Requests;

use App\Models\PersonaUnidadVinculo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePersonaUnidadVinculoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('personas.gestionar');
    }

    public function rules(): array
    {
        return [
            'unidad_servicio_id' => ['required', 'integer', Rule::exists('unidades_servicios', 'id')],
            'estamento_id' => ['nullable', 'integer', Rule::exists('estamentos', 'id')],
            'profesion_id' => ['nullable', 'integer', Rule::exists('profesiones', 'id')],
            'cargo_texto' => ['nullable', 'string', 'max:190'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'status' => ['required', Rule::in(PersonaUnidadVinculo::ESTADOS)],
        ];
    }
}
