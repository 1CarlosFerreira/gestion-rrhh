<?php

namespace App\Http\Requests\Admin;

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
                'max:20',
                Rule::unique('personas')->ignore($personaId),
                'regex:/^[0-9]+[-|‐]{1}[0-9kK]{1}$/'
            ],
            'nombres' => ['required', 'string', 'max:255'],
            'apellido_paterno' => ['required', 'string', 'max:255'],
            'apellido_materno' => ['nullable', 'string', 'max:255'],
            'active' => ['required', 'boolean'],
        ];
    }
}