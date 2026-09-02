<?php

namespace App\Http\Requests;

use App\Models\Persona;
use App\Rules\ValidRut;
use App\Support\Rut\Rut;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveReemplazoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('reemplazos.crear');
    }

    public function rules(): array
    {
        return [
            'unidad_servicio_id' => [Rule::requiredIf($this->routeIs('reemplazos.store')), Rule::exists('unidades_servicios', 'id')->where('activo', true)],
            'tipo_reemplazo_id' => ['nullable', Rule::exists('tipos_reemplazo', 'id')->where('activo', true)],
            'funcionario_id' => ['nullable', Rule::exists('personas', 'id')],
            'funcionario_vinculo_id' => ['nullable', Rule::exists('persona_unidad_vinculos', 'id')],
            'reemplazante_id' => ['nullable', Rule::exists('personas', 'id')],
            'nuevo_reemplazante_rut' => ['nullable', 'string', new ValidRut],
            'nuevo_reemplazante_nombres' => ['nullable', 'string', 'max:120'],
            'nuevo_reemplazante_apellido_paterno' => ['nullable', 'string', 'max:100'],
            'nuevo_reemplazante_apellido_materno' => ['nullable', 'string', 'max:100'],
            'estamento_id' => ['nullable', Rule::exists('estamentos', 'id')],
            'profesion_id' => ['nullable', Rule::exists('profesiones', 'id')],
            'cargo_texto' => ['nullable', 'string', 'max:190'],
            'justificacion' => ['nullable', 'string', 'max:5000'],
            'fecha_inicio_ausencia' => ['nullable', 'date'],
            'fecha_termino_ausencia' => ['nullable', 'date', 'after_or_equal:fecha_inicio_ausencia'],
            'fecha_inicio' => ['nullable', 'date'],
            'fecha_termino' => ['nullable', 'date', 'after_or_equal:fecha_inicio'],
            'accion' => ['nullable', Rule::in(['guardar', 'adjuntar', 'enviar'])],
            'archivo' => ['required_if:accion,adjuntar', 'nullable', 'file', 'max:10240', 'mimes:pdf,doc,docx,xls,xlsx,csv,jpg,jpeg,png'],
            'tipo_documento_id' => ['nullable', 'integer', Rule::exists('tipos_documento', 'id')->where('active', true)],
        ];
    }

    public function messages(): array
    {
        return [
            'unidad_servicio_id.required' => $this->input('accion') === 'adjuntar'
                ? 'No fue posible adjuntar el documento porque faltan antecedentes necesarios para guardar el borrador.'
                : 'La Unidad/Servicio es obligatoria.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('nuevo_reemplazante_rut')) {
            $this->merge(['nuevo_reemplazante_rut' => Rut::normalize($this->input('nuevo_reemplazante_rut'))]);
        }
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $funcionarioId = $this->integer('funcionario_id') ?: $this->route('tramite')?->reemplazo?->funcionario_id;
            $reemplazanteId = $this->integer('reemplazante_id');

            if ($reemplazanteId && $this->filled('nuevo_reemplazante_rut')) {
                $message = 'Seleccione una persona existente o registre un nuevo reemplazante, no ambas opciones.';
                $validator->errors()->add('reemplazante_id', $message);
                $validator->errors()->add('nuevo_reemplazante_rut', $message);
            }

            if ($funcionarioId && $reemplazanteId && $funcionarioId === $reemplazanteId) {
                $validator->errors()->add('reemplazante_id', 'El reemplazante propuesto no puede ser la misma persona que el funcionario a reemplazar.');
            }

            if (! $this->filled('nuevo_reemplazante_rut')) {
                return;
            }

            $persona = Persona::query()->where('rut', (string) $this->string('nuevo_reemplazante_rut'))->first();
            if ($persona?->id === $funcionarioId) {
                $validator->errors()->add('nuevo_reemplazante_rut', 'Esta persona corresponde al funcionario que está siendo reemplazado y no puede registrarse como reemplazante.');
            } elseif (! $persona && ! $this->filled('nuevo_reemplazante_nombres')) {
                $validator->errors()->add('nuevo_reemplazante_nombres', 'Los nombres son obligatorios para registrar un nuevo reemplazante.');
            }
        }];
    }
}
