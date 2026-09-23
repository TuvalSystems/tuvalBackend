<?php

namespace App\Http\Requests\Venta\Consultas;

use Illuminate\Foundation\Http\FormRequest;

class BusquedaArticuloRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'puntodeventaselect'    => 'required|string|exists:gp_punto_ventas,PTO_VTA_ID',
            'nivel_precio'          => 'nullable|string',
            'codigo3l'              => 'nullable|string',
            'text_codigo'           => 'nullable|string',
            'select_codigo'         => 'nullable|string',
            'select_nombre'         => 'nullable|string',
            'text_nombre'           => 'nullable|string',
            // 'terminal'              => 'nullable|boolean',
        ];
    }

    public function messages()
    {
       return [
            'puntodeventaselect.required' => 'Punto de venta es obligatorio.',
            'puntodeventaselect.exists' => 'Punto de venta no existe en el sistema.',
        ];
    }
}
