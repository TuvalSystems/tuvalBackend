<?php

namespace App\Http\Requests\Venta\Cotizaciones;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreQuotationItemsDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->isMethod('GET') && app()->environment(['local', 'testing', 'qa', 'staging']);
    }

    public function validationData(): array
    {
        return $this->isJson() ? $this->json()->all() : [];
    }

    public function rules(): array
    {
        return [
            'cliente_ruc' => ['required', 'string', 'max:30'],
            'pv_id' => ['required', 'integer', 'min:1'],
            'items' => ['required', 'array', 'list', 'min:1', 'max:20'],
            'items.*' => ['required', 'array:codigo,cantidad'],
            'items.*.codigo' => ['required', 'string', 'max:30', 'distinct:strict'],
            // The persisted detail has decimal(11,2). Never silently round a request.
            'items.*.cantidad' => ['required', 'numeric', 'gt:0', 'max:1000000', 'regex:/^\d+(\.\d{1,2})?$/D'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            foreach (array_diff(array_keys($validator->getData()), ['cliente_ruc', 'pv_id', 'items']) as $key) {
                $validator->errors()->add($key, 'Campo no admitido.');
            }
        });
    }
}
