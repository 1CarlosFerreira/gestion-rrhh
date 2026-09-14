<?php

namespace App\Http\Requests\Admin;

use App\Rules\ValidRut;
use App\Support\Rut\Rut;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SavePersonaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('personas.gestionar');
    }

    public function rules(): array
    {
        $personaId = $this->route('persona')?->id;

        return [
            'rut' => [
                'required',
                'string',
                'max:12',
                new ValidRut,
                Rule::unique('personas')->ignore($personaId),
            ],
            'nombres' => ['required', 'string', 'max:120'],
            'apellido_paterno' => ['nullable', 'string', 'max:100'],
            'apellido_materno' => ['nullable', 'string', 'max:100'],
            'active' => $this->isMethod('post') ? ['required', 'boolean'] : ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('rut'))) {
            $this->merge(['rut' => Rut::normalize($this->input('rut'))]);
        }
    }

    public function attributes(): array
    {
        return ['rut' => 'RUT'];
    }
}
