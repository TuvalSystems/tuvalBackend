<?php

declare(strict_types=1);

namespace App\Http\Requests\Reportes\Inventory;

use App\Traits\JsonResponseTrait;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class ReplenishmentReceiptHistoryRequest extends FormRequest
{
    use JsonResponseTrait;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'periodo' => ['bail', 'required', 'string', 'max:32', 'regex:/\A[0-9]{4}(0[1-9]|1[0-2])\z/'],
            'numero_reposicion' => ['nullable', 'string', 'max:255'],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            $this->errorResponse('Los parámetros de consulta no son válidos.', $validator->errors()->toArray(), 422)
        );
    }
}
