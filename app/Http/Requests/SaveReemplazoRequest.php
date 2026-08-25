<?php

namespace App\Http\Requests;

use App\Rules\ValidRut;
use App\Support\Rut\Rut;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveReemplazoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('reemplazos.crear');
    }

    public function rules(): array
    {
        return [
            'unidad_servicio_id' => ['sometimes', 'required', Rule::exists('unidades_servicios', 'id')->where('activo', true)],
            'tipo_reemplazo_id' => ['nullable', Rule::exists('tipos_reemplazo', 'id')->where('activo', true)],
            'funcionario_id' => ['nullable', Rule::exists('personas', 'id')],
            'funcionario_vinculo_id' => ['nullable', Rule::exists('persona_unidad_vinculos', 'id')],
            'reemplazante_id' => ['nullable', Rule::exists('personas', 'id')],
            'nuevo_reemplazante_rut' => ['nullable', 'string', new ValidRut],
            'nuevo_reemplazante_nombres' => ['nullable', 'required_with:nuevo_reemplazante_rut', 'string', 'max:120'],
            'nuevo_reemplazante_apellido_paterno' => ['nullable', 'string', 'max:100'],
            'nuevo_reemplazante_apellido_materno' => ['nullable', 'string', 'max:100'],
            'estamento_id' => ['nullable', Rule::exists('estamentos', 'id')],
            'profesion_id' => ['nullable', Rule::exists('profesiones', 'id')],
            'cargo_texto' => ['nullable', 'string', 'max:190'],
            'justificacion' => ['nullable', 'string', 'max:5000'],
            'fecha_inicio' => ['nullable', 'date'],
            'fecha_termino' => ['nullable', 'date', 'after_or_equal:fecha_inicio'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('nuevo_reemplazante_rut')) {
            $this->merge(['nuevo_reemplazante_rut' => Rut::normalize($this->input('nuevo_reemplazante_rut'))]);
        }
    }
}
