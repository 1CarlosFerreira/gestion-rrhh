<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewPlanillaSirhRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('horas_extra.revisar_planilla') ?? false;
    }

    public function rules(): array
    {
        return [
            'result' => ['required', Rule::in(['CONFORME', 'OBSERVADA'])],
            'observation' => ['nullable', 'required_if:result,OBSERVADA', 'string', 'max:5000'],
        ];
    }
}
