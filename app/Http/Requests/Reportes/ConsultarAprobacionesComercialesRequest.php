<?php

declare(strict_types=1);

namespace App\Http\Requests\Reportes;

use App\Traits\JsonResponseTrait;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class ConsultarAprobacionesComercialesRequest extends FormRequest
{
    use JsonResponseTrait;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];
        foreach (['cotizacion', 'factura', 'cliente', 'sucursal', 'aprobador_final', 'periodo'] as $key) {
            $value = $this->input($key);
            if (is_string($value)) {
                $normalized[$key] = trim($value) === '' ? null : trim($value);
            }
        }
        $this->merge($normalized);
    }

    public function rules(): array
    {
        return [
            'cotizacion' => ['nullable', 'string', 'max:100'],
            'factura' => ['nullable', 'string', 'max:100'],
            'cliente' => ['nullable', 'string', 'max:100'],
            'sucursal' => ['nullable', 'string', 'max:10'],
            'aprobador_final' => ['nullable', 'string', 'max:100'],
            'periodo' => ['bail', 'required', 'string', 'regex:/\A[1-9][0-9]{3}(0[1-9]|1[0-2])\z/', 'not_in:999912'],
            'tipo_documento' => ['prohibited'],
            'documento_id' => ['prohibited'],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            $this->validationErrorResponse($validator, 'Los parámetros de consulta no son válidos.')
        );
    }
}
