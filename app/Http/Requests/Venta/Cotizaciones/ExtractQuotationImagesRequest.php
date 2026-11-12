<?php

namespace App\Http\Requests\Venta\Cotizaciones;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ExtractQuotationImagesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->isMethod('POST') && $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'cliente_ruc' => ['required', 'string', 'max:30'],
            'pv_id' => ['required', 'integer', 'min:1'],
            'maxCandidatos' => ['sometimes', 'integer', 'min:1', 'max:6'],
            'imagenes' => ['required', 'array', 'list', 'min:1', 'max:3'],
            'imagenes.*' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:max_width=10000,max_height=10000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach (array_diff(array_keys($validator->getData()), array_keys($this->rules())) as $key) {
                $validator->errors()->add($key, 'Campo no admitido.');
            }
        });
    }
}
