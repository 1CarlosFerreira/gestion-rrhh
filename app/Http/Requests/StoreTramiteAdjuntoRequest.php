<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTramiteAdjuntoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('tramites.adjuntos.cargar');
    }

    public function rules(): array
    {
        return [
            'archivo' => ['required', 'file', 'max:10240', 'mimes:pdf,doc,docx,xls,xlsx,csv,jpg,jpeg,png'],
            'tipo_documento_id' => ['nullable', 'integer', Rule::exists('tipos_documento', 'id')->where('active', true)],
            'persona_id' => ['nullable', 'integer', Rule::exists('personas', 'id')],
        ];
    }
}
