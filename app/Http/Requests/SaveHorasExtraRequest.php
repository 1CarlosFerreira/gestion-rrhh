<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveHorasExtraRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('horas_extra.crear') ?? false;
    }

    public function rules(): array
    {
        return [
            'unidad_servicio_id' => ['required', 'integer', Rule::exists('unidades_servicios', 'id')->where('activo', true)],
            'year' => ['required', 'integer', 'between:2000,2100'],
            'month' => ['required', 'integer', 'between:1,12'],
            'persona_ids' => ['required', 'array', 'min:1'],
            'persona_ids.*' => ['required', 'integer', 'distinct', Rule::exists('personas', 'id')->where('active', true)],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $personIds = array_map('intval', $this->input('persona_ids', []));
            $validCount = DB::table('persona_unidad_vinculos')
                ->where('unidad_servicio_id', $this->integer('unidad_servicio_id'))
                ->where('status', 'ACTIVO')
                ->whereIn('persona_id', $personIds)
                ->where(fn ($query) => $query->whereNull('start_date')->orWhere('start_date', '<=', now()->toDateString()))
                ->where(fn ($query) => $query->whereNull('end_date')->orWhere('end_date', '>=', now()->toDateString()))
                ->distinct()
                ->count('persona_id');

            if ($validCount !== count($personIds)) {
                $validator->errors()->add('persona_ids', 'Todos los funcionarios seleccionados deben tener un vínculo ACTIVO y vigente en la unidad elegida.');
            }
        }];
    }
}
