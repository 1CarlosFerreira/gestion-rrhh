<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegisterHorasExtraRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('horas_extra.registrar_horas') ?? false;
    }

    public function rules(): array
    {
        return [
            'day_hours' => ['required', 'integer', 'min:0'],
            'day_minutes' => ['required', 'integer', 'between:0,59'],
            'night_hours' => ['required', 'integer', 'min:0'],
            'night_minutes' => ['required', 'integer', 'between:0,59'],
        ];
    }
}
