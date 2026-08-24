<?php

namespace App\Http\Requests;

use App\Rules\ValidRut;
use App\Support\Rut\Rut;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePersonaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('personas.gestionar');
    }

    public function rules(): array
    {
        return [
            'rut' => ['required', 'string', 'max:12', new ValidRut, Rule::unique('personas', 'rut')],
            'nombres' => ['required', 'string', 'max:120'],
            'apellido_paterno' => ['nullable', 'string', 'max:100'],
            'apellido_materno' => ['nullable', 'string', 'max:100'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['rut' => Rut::normalize($this->input('rut'))]);
    }
}
