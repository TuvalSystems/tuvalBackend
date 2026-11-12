<?php

namespace App\Mcp\Tools;

use App\Traits\ExecuteApiTransactions;
use App\Traits\JsonResponseTrait;
use Closure;
use Illuminate\Http\JsonResponse;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;

abstract class TuvalQuotationTool extends Tool
{
    use ExecuteApiTransactions, JsonResponseTrait;

    protected function respond(Closure $action): Response|ResponseFactory
    {
        $response = $this->handleApiAction(
            fn (): JsonResponse => $this->successResponse('Proceso realizado correctamente.', $action()),
            'cotizaciones',
            $this->name()
        );
        $body = $response->getData(true);

        if ($response->getStatusCode() >= 400) {
            return Response::error(json_encode([
                ...$body,
                'status' => $response->getStatusCode(),
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
        }

        return Response::structured($body);
    }
}
