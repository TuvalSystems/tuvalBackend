<?php

declare(strict_types=1);

namespace App\Http\Requests\Reportes;

use App\Traits\JsonResponseTrait;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class ConsultarAprobacionesComercialesRequest extends FormRequest
{
    use JsonResponseTrait;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tipo_documento' => ['required', 'string', Rule::in(['PEDIDO', 'COTIZACION'])],
            'documento_id' => ['bail', 'required', 'integer', 'min:1', 'regex:/\A[1-9][0-9]*\z/'],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            $this->validationErrorResponse($validator, 'Los parámetros de consulta no son válidos.')
        );
    }
}
