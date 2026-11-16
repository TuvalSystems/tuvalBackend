<?php

namespace App\Services\Ventas\Compartido\Contracts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;

interface IdempotentSaveServiceInterface
{
    public function requestData(FormRequest $request): array;

    public function execute(string $tipo, int $usuarioId, array $data, string $modelo, callable $guardar): Model;
}
