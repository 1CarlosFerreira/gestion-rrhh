<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveCalidadContractualRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('calidades_contractuales.gestionar') === true;
    }

    public function rules(): array
    {
        $id = $this->route('calidad')?->id;

        return ['codigo' => ['required', 'string', 'max:50', Rule::unique('calidades_contractuales')->ignore($id)], 'nombre' => ['required', 'string', 'max:150'], 'descripcion' => ['nullable', 'string', 'max:2000'], 'orden' => ['required', 'integer', 'min:0'], 'activo' => ['nullable', 'boolean']];
    }
}
