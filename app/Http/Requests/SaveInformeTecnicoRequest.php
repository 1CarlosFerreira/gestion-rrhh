<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveInformeTecnicoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('documentos.generar') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'horario_diurno' => $this->boolean('horario_diurno'),
            'horario_festivo' => $this->boolean('horario_festivo'),
            'retribucion_tiempo' => $this->boolean('retribucion_tiempo'),
            'retribucion_dinero' => $this->boolean('retribucion_dinero'),
        ]);
    }

    public function rules(): array
    {
        return [
            'horario_diurno' => ['required', 'boolean'], 'horario_festivo' => ['required', 'boolean'],
            'retribucion_tiempo' => ['required', 'boolean'], 'retribucion_dinero' => ['required', 'boolean'],
            'justificacion_tecnica' => ['required', 'string', 'max:10000'],
            'medidas_control' => ['required', 'string', 'max:10000'],
        ];
    }
}
