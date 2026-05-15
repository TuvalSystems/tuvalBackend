<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSectionRequest extends FormRequest
{
     public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $parentId = $this->input('parent_id');

        $this->merge([
            /*
             * Compatibilidad temporal con el frontend anterior.
             */
            'route_name' => $this->input('route_name', $this->input('to')),

            /*
             * Compatibilidad temporal con el frontend anterior.
             */
            'sort_order' => $this->input('sort_order', $this->input('order', 0)),

            /*
             * parent_id = 0 o vacío ahora significa raíz.
             */
            'parent_id' => empty($parentId) || (int) $parentId === 0
                ? null
                : (int) $parentId,

            'platform' => $this->input('platform', 'web'),

            'status' => $this->has('status')
                ? $this->boolean('status')
                : true,
        ]);
    }

    public function rules(): array
    {
        $section = $this->route('section');

        $sectionId = is_object($section)
            ? $section->id
            : $section;

        return [
            'parent_id' => [
                'nullable',
                'integer',
                'exists:sections,id',
                Rule::notIn([$sectionId]),
            ],

            'route_name' => [
                'nullable',
                'string',
                'max:100',
            ],

            'url' => [
                'nullable',
                'string',
                'max:255',
            ],

            'name' => [
                'required',
                'string',
                'max:100',
            ],

            'icon' => [
                'nullable',
                'string',
                'max:100',
            ],

            'sort_order' => [
                'nullable',
                'integer',
                'min:0',
            ],

            'platform' => [
                'required',
                Rule::in(['web', 'app', 'both']),
            ],

            'status' => [
                'required',
                'boolean',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'El nombre de la sección es obligatorio.',
            'name.max' => 'El nombre de la sección no puede superar los 100 caracteres.',

            'parent_id.exists' => 'La sección padre seleccionada no existe.',
            'parent_id.not_in' => 'Una sección no puede ser padre de sí misma.',

            'route_name.max' => 'El nombre de ruta no puede superar los 100 caracteres.',

            'url.max' => 'La URL no puede superar los 255 caracteres.',

            'icon.max' => 'El ícono no puede superar los 100 caracteres.',

            'sort_order.integer' => 'El orden debe ser un número entero.',
            'sort_order.min' => 'El orden no puede ser menor a cero.',

            'platform.required' => 'La plataforma es obligatoria.',
            'platform.in' => 'La plataforma seleccionada no es válida.',

            'status.required' => 'El estado es obligatorio.',
            'status.boolean' => 'El estado debe ser activo o inactivo.',
        ];
    }
}
