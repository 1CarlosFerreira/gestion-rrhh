<?php

namespace App\Http\Requests;

use App\Models\Tramite;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class RegistrarRespaldoTransitorioRequest extends FormRequest
{
    public function authorize(): bool
    {
        $tramite = $this->route('tramite');

        return $tramite instanceof Tramite && Gate::allows('editar-reemplazo', $tramite);
    }

    public function rules(): array
    {
        return [
            'motivo' => ['required', 'string', 'max:2000'],
            'fecha_desde' => ['required', 'date_format:Y-m-d'],
            'fecha_hasta' => ['required', 'date_format:Y-m-d', 'after_or_equal:fecha_desde'],
            'referencia_externa' => ['nullable', 'string', 'max:200'],
        ];
    }
}
