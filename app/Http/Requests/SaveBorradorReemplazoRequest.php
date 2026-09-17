<?php

namespace App\Http\Requests;

use App\Models\Persona;
use App\Models\Profesion;
use App\Rules\ValidRut;
use App\Support\Rut\Rut;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveBorradorReemplazoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('reemplazos.crear') === true;
    }

    public function rules(): array
    {
        return ['unidad_organizacional_id' => ['required', 'exists:unidades_organizacionales,id'], 'funcionario_id' => ['nullable', 'exists:personas,id'], 'tipo_reemplazo_id' => ['nullable', Rule::exists('tipos_reemplazo', 'id')->where('activo', true)], 'reemplazante_id' => ['nullable', 'exists:personas,id'], 'reemplazante_estamento_id' => ['nullable', Rule::exists('estamentos', 'id')->where('activo', true)], 'reemplazante_profesion_id' => ['nullable', Rule::exists('profesiones', 'id')->where('activo', true)], 'reemplazante_calidad_contractual_id' => ['nullable', Rule::exists('calidades_contractuales', 'id')->where('activo', true)], 'reemplazante_cargo_funcion' => ['nullable', 'string', 'max:200'], 'nuevo_reemplazante_rut' => ['nullable', 'string', new ValidRut], 'nuevo_reemplazante_nombres' => ['nullable', 'string', 'max:120'], 'nuevo_reemplazante_apellido_paterno' => ['nullable', 'string', 'max:100'], 'nuevo_reemplazante_apellido_materno' => ['nullable', 'string', 'max:100'], 'fecha_funcionario_desde' => ['nullable', 'date'], 'fecha_funcionario_hasta' => ['nullable', 'date'], 'fecha_reemplazante_desde' => ['nullable', 'date'], 'fecha_reemplazante_hasta' => ['nullable', 'date'], 'justificacion' => ['nullable', 'string', 'max:5000']];
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('nuevo_reemplazante_rut')) {
            $this->merge(['nuevo_reemplazante_rut' => Rut::normalize($this->input('nuevo_reemplazante_rut'))]);
        }
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($this->filled('reemplazante_id') && $this->filled('nuevo_reemplazante_rut')) {
                $validator->errors()->add('reemplazante_id', 'Seleccione una persona existente o registre una nueva, no ambas.');
            }
            if ($this->filled('nuevo_reemplazante_rut') && ! $this->filled('nuevo_reemplazante_nombres') && ! Persona::query()->where('rut', $this->input('nuevo_reemplazante_rut'))->exists()) {
                $validator->errors()->add('nuevo_reemplazante_nombres', 'Los nombres son obligatorios para una persona nueva.');
            }
            if ($this->filled('reemplazante_profesion_id') && $this->filled('reemplazante_estamento_id')) {
                $incompatible = Profesion::query()->whereKey($this->integer('reemplazante_profesion_id'))->whereNotNull('estamento_id')->where('estamento_id', '!=', $this->integer('reemplazante_estamento_id'))->exists();
                if ($incompatible) {
                    $validator->errors()->add('reemplazante_profesion_id', 'La profesión no corresponde al estamento seleccionado.');
                }
            }
        }];
    }
}
