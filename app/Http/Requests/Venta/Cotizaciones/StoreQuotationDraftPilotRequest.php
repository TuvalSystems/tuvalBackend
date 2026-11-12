<?php

namespace App\Http\Requests\Venta\Cotizaciones;

use Illuminate\Support\Facades\Validator;

class StoreQuotationDraftPilotRequest extends StoreQuotationItemsDraftRequest
{
    public function authorize(): bool
    {
        return $this->isMethod('POST') && $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            ...parent::rules(),
            'request_id' => ['required', 'uuid'],
            'confirmado' => ['required', 'boolean', 'accepted'],
        ];
    }

    public static function validateInput(array $data): array
    {
        $request = new static();
        $validator = Validator::make($data, $request->rules());
        $request->withValidator($validator);

        return $validator->validate();
    }
}
