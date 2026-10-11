<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AnulacionTransitoriaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->active === true;
    }

    public function rules(): array
    {
        return ['motivo' => ['required', 'string', 'max:2000']];
    }
}
