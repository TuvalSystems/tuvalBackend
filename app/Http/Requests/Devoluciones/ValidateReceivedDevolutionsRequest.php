<?php

namespace App\Http\Requests\Devoluciones;

use Illuminate\Foundation\Http\FormRequest;

class ValidateReceivedDevolutionsRequest extends FormRequest
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
            'header.id' => 'required|integer|exists:gp_devoluciones,id',
            'details' => [
                'required',
                'array',
                'min:1',
                function ($attribute, $value, $fail) {
                    $totalDevuelto = collect($value)->sum(function ($item) {
                        return floatval($item['new_devuelto'] ?? 0);
                    });
                    if ($totalDevuelto <= 0)
                        $fail('El total devuelto debe ser mayor a 0.');
                }
            ],
            'details.*.new_devuelto' => 'required|numeric|min:0',
            'details.*' => function ($attribute, $value, $fail) {
                $devuelto = floatval($value['new_devuelto'] ?? 0);
                $disponible = floatval($value['pending_received_devolution'] ?? 0);
                $compartimiento = $value['compartimiento'] === null;
                if ($devuelto > 0 && $devuelto > $disponible) {
                    $fail("La cantidad devuelta ({$devuelto}) excede lo disponible ({$disponible}).");
                }
                if ($devuelto > 0 && (is_null($compartimiento) || $compartimiento === '')) {
                    $fail("El compartimiento no puede estar vacio.");
                }
            },


        ];
    }

    public function attributes()
    {
        return [
            'header.id' => 'ID de la devolución',
            'details' => 'detalle de la devolucion',
            'details.*.new_devuelto' => 'cantidad a devolver',
        ];
    }
}
