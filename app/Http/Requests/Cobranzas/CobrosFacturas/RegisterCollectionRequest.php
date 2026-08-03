<?php

namespace App\Http\Requests\Cobranzas\CobrosFacturas;

use Illuminate\Foundation\Http\FormRequest;

class RegisterCollectionRequest extends FormRequest
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
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'cliente_id' => [
                'required',
                'integer',
                'exists:gp_clientes,id',
            ],

            'pv_id' => [
                'required',
                'integer',
                'exists:gp_punto_ventas,id',
            ],

            'caja_id' => [
                'required',
                'integer',
                'exists:gp_cajas,id',
            ],

            'pvCodigo' => [
                'required',
                'string',
            ],

            'referencia' => [
                'nullable',
                'string',
            ],

            'retenciones' => [
                'present',
                'array',
            ],

            'depositos' => [
                'present',
                'array',
            ],

            'aplicaciones' => [
                'present',
                'array',
            ],

            'efectivo' => [
                'nullable',
                'array',
            ],

            'tarjeta' => [
                'nullable',
                'array',
            ],

            'cheque' => [
                'nullable',
                'array',
            ],
        ];
    }
}
