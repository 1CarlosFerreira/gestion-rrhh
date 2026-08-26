<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegistrarFormalizacionDocDigitalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('docdigital.registrar_formalizacion') === true;
    }

    public function rules(): array
    {
        return [
            'fecha_formalizacion' => ['required', 'date'],
            'identificador_externo' => ['nullable', 'string', 'max:190'],
            'observacion_formalizacion' => ['nullable', 'string', 'max:5000'],
            'archivo_final' => ['required', 'file', 'max:10240', 'mimes:pdf,doc,docx,xls,xlsx,csv,jpg,jpeg,png'],
        ];
    }
}
