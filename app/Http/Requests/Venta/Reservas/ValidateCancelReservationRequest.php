<?php

namespace App\Http\Requests\Venta\Reservas;

use Illuminate\Foundation\Http\FormRequest;

class ValidateCancelReservationRequest extends FormRequest
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
            'id' => 'required|integer|exists:gp_reservas,id',
        ];
    }

    public function messages()
    {
        return [
            'id.required' => 'El ID de la reserva es obligatorio.',
            'id.exists' => 'La reserva especificada no existe en el sistema.',
        ];
    }



}
