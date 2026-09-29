<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexTramitesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->active === true && $this->user()->can('tramites.ver_todos');
    }

    public function rules(): array
    {
        $tipo = $this->integer('tipo');

        return [
            'buscar' => ['nullable', 'string', 'max:120'],
            'tipo' => ['nullable', 'integer', Rule::exists('tipos_tramite', 'id')],
            'estado' => [
                'nullable',
                'integer',
                Rule::exists('estados_tramite', 'id')->when(
                    $tipo > 0,
                    fn ($rule) => $rule->where('tipo_tramite_id', $tipo),
                ),
            ],
            'unidad' => ['nullable', 'integer', Rule::exists('unidades_organizacionales', 'id')],
            'creador' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'fecha_desde' => ['nullable', 'date_format:Y-m-d'],
            'fecha_hasta' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:fecha_desde'],
            'orden' => ['nullable', Rule::in(['recientes', 'antiguos'])],
            'per_page' => ['required', 'integer', Rule::in([20, 50, 100])],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $perPage = filter_var($this->input('per_page'), FILTER_VALIDATE_INT);

        $this->merge([
            'per_page' => in_array($perPage, [20, 50, 100], true) ? $perPage : 20,
            'orden' => $this->input('orden', 'recientes'),
        ]);
    }

    public function attributes(): array
    {
        return [
            'buscar' => 'búsqueda',
            'tipo' => 'tipo de trámite',
            'estado' => 'estado',
            'unidad' => 'unidad organizacional',
            'creador' => 'creado por',
            'fecha_desde' => 'fecha desde',
            'fecha_hasta' => 'fecha hasta',
            'orden' => 'orden',
            'per_page' => 'cantidad por página',
        ];
    }
}
