<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSectionRequest extends FormRequest
{

    public function authorize()
    {
        return true;
    }


    protected function prepareForValidation(): void
    {
        $parentId = $this->input('parent_id');

        $this->merge([
            /*
             * Compatibilidad temporal con el frontend anterior:
             * antes enviaba "to", ahora usamos "route_name".
             */
            'route_name' => $this->input('route_name', $this->input('to')),

            /*
             * Compatibilidad temporal:
             * antes enviaba "order", ahora usamos "sort_order".
             */
            'sort_order' => $this->input('sort_order', $this->input('order', 0)),

            /*
             * Antes algunos registros usaban parent_id = 0.
             * Ahora las secciones raíz deben usar parent_id = null.
             */
            'parent_id' => empty($parentId) || (int) $parentId === 0
                ? null
                : (int) $parentId,

            'platform' => $this->input('platform', 'web'),

            /*
             * Si no envían status, por defecto activo.
             */
            'status' => $this->has('status')
                ? $this->boolean('status')
                : true,
        ]);
    }

    public function rules(): array
    {
        return [
            'parent_id' => [
                'nullable',
                'integer',
                'exists:sections,id',
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
