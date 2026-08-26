<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegistrarEnvioDocDigitalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('docdigital.registrar_envio') === true;
    }

    public function rules(): array
    {
        return [
            'adjunto_enviado_id' => ['required', 'integer', Rule::exists('tramite_adjuntos', 'id')->where(fn ($query) => $query->where('tramite_id', $this->route('tramite')->id)->where('status', 'ACTIVO'))],
            'fecha_envio' => ['required', 'date'],
            'identificador_externo' => ['nullable', 'string', 'max:190'],
            'observacion_envio' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
