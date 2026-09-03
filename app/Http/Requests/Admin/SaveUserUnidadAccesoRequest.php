<?php

namespace App\Http\Requests\Admin;

use App\Enums\AlcanceAccesoOperativo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveUserUnidadAccesoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('accesos_operativos.gestionar') === true;
    }

    public function rules(): array
    {
        return ['user_id' => ['required', 'exists:users,id'], 'unidad_organizacional_id' => ['required', Rule::exists('unidades_organizacionales', 'id')->where('activo', true)], 'alcance' => ['required', Rule::enum(AlcanceAccesoOperativo::class)], 'vigente_desde' => ['required', 'date'], 'vigente_hasta' => ['nullable', 'date', 'after_or_equal:vigente_desde'], 'observacion' => ['nullable', 'string', 'max:2000']];
    }
}
