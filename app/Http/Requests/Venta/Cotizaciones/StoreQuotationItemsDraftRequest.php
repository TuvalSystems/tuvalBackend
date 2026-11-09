<?php

namespace App\Http\Requests\Venta\Cotizaciones;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use Illuminate\Validation\Rule;

class StoreQuotationItemsDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->status
            && $this->user()->perfil?->state === 'active';
    }

    public function rules(): array
    {
        return [
            'request_id' => ['required', 'uuid'],
            'modo_prueba' => ['sometimes', 'required', 'boolean'],
            'user_id' => ['sometimes', 'required', 'integer', 'min:1', Rule::prohibitedIf(!$this->boolean('modo_prueba'))],
            'cliente_ruc' => [$this->boolean('modo_prueba') ? 'sometimes' : 'required', 'required', 'string', 'max:30'],
            'pv_id' => [$this->boolean('modo_prueba') ? 'sometimes' : 'required', 'required', 'integer', 'min:1'],
            'items' => ['required', 'array', 'list', 'min:1', 'max:20'],
            'items.*' => ['required', 'array:codigo,cantidad,unidad'],
            'items.*.codigo' => ['required', 'string', 'max:30', 'distinct:strict'],
            // The persisted detail has decimal(11,2). Never silently round a request.
            'items.*.cantidad' => ['sometimes', 'required', 'numeric', 'gt:0', 'max:1000000', 'regex:/^\d+(\.\d{1,2})?$/D'],
            'items.*.unidad' => ['sometimes', 'required', 'string', 'max:30'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            foreach (array_diff(array_keys($this->all()), ['request_id', 'cliente_ruc', 'pv_id', 'items', 'modo_prueba', 'user_id']) as $key) {
                $validator->errors()->add($key, 'Campo no admitido.');
            }
        });
    }
}
