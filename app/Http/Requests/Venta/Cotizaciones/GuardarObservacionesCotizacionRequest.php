<?php

namespace App\Http\Requests\Venta\Cotizaciones;

use Illuminate\Foundation\Http\FormRequest;

class GuardarObservacionesCotizacionRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'id' => 'required|integer|min:1',
            'orden_de_compra' => 'present|nullable|string|max:100',
            'nota_interna' => 'present|nullable|string|max:2000',
        ];
    }
}
