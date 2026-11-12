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
            'detalle' => ['required', 'array', 'list', 'min:1', 'max:20'],
            'detalle.*' => ['required', 'array:texto,cantidad'],
            'detalle.*.texto' => ['required', 'string', 'max:200'],
            // The persisted detail has decimal(11,2). Never silently round a request.
            'detalle.*.cantidad' => ['nullable', 'numeric', 'gt:0', 'max:1000000', 'regex:/^\d+(\.\d{1,2})?$/D'],
            'maxCandidatos' => ['sometimes', 'integer', 'min:1', 'max:6'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            foreach (array_diff(array_keys($validator->getData()), ['cliente_ruc', 'pv_id', 'detalle', 'maxCandidatos']) as $key) {
                $validator->errors()->add($key, 'Campo no admitido.');
            }
        });
    }
}
