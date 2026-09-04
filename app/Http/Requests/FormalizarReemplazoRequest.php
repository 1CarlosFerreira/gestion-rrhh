<?php

namespace App\Http\Requests;

use App\Models\CalidadContractual;
use App\Models\Estamento;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class FormalizarReemplazoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('reemplazos.formalizar') === true;
    }

    public function rules(): array
    {
        return [
            'estamento_id' => ['required', 'integer', Rule::exists('estamentos', 'id')->where('activo', true)],
            'profesion_id' => ['nullable', 'integer', Rule::exists('profesiones', 'id')->where('activo', true)],
            'calidad_contractual_id' => ['required', 'integer', Rule::exists('calidades_contractuales', 'id')->where('activo', true)],
            'cargo_funcion' => ['required', 'string', 'max:200'],
            'identificador_externo' => ['nullable', 'string', 'max:255'],
            'observacion' => ['nullable', 'string', 'max:5000'],
            'documento_final' => ['nullable', 'file', 'max:10240', 'mimes:pdf,doc,docx,jpg,jpeg,png'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if (! Estamento::query()->where('activo', true)->exists()) {
                $validator->errors()->add('catalogos', 'No existen estamentos activos configurados. Debe configurar el catálogo antes de formalizar este reemplazo.');
            }
            if (! CalidadContractual::query()->where('activo', true)->exists()) {
                $validator->errors()->add('catalogos', 'No existen calidades contractuales activas configuradas. Debe configurar el catálogo antes de formalizar este reemplazo.');
            }
        }];
    }
}
