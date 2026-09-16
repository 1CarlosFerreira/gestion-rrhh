<?php

namespace App\Http\Requests\Admin;

use App\Enums\OrigenVinculoDotacion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SavePersonaUnidadVinculoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('dotacion.gestionar') === true;
    }

    public function rules(): array
    {
        return ['persona_id' => ['required', 'exists:personas,id'], 'unidad_organizacional_id' => ['required', 'exists:unidades_organizacionales,id'], 'estamento_id' => ['required', 'exists:estamentos,id'], 'profesion_id' => ['nullable', 'exists:profesiones,id'], 'calidad_contractual_id' => ['required', 'exists:calidades_contractuales,id'], 'cargo_funcion' => ['required', 'string', 'max:200'], 'grado_eus' => ['nullable', 'integer', 'min:1', 'max:99'], 'vigente_desde' => ['required', 'date'], 'vigente_hasta' => ['nullable', 'date', 'after_or_equal:vigente_desde'], 'origen' => ['required', Rule::in([OrigenVinculoDotacion::MANUAL->value, OrigenVinculoDotacion::IMPORTACION->value])], 'origen_tramite_id' => ['prohibited'], 'observacion' => ['nullable', 'string', 'max:2000']];
    }
}
