<?php

namespace App\Http\Controllers\Inventario\Articulo;

use App\Http\Controllers\Controller;
use App\Services\Inventario\Articulo\Contracts\ArticleSyncServiceInterface;
use App\Traits\ExecuteApiTransactions;
use App\Traits\JsonResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ArticuloController extends Controller
{
    use JsonResponseTrait, ExecuteApiTransactions;
    public function __construct(protected readonly ArticleSyncServiceInterface $service)
    {
        throw new \Exception('Not implemented');
    }

    public function sincronizarDesdeApi(Request $request): JsonResponse
    {
        return $this->handleApiAction(function () use ($request): JsonResponse {
            $payload = $request->all();
            $resp = $this->service->createOrUpdateArticle($payload);
            return $this->successResponse('Articulo creado o actualizado con exito.', $resp, 200);
        }, 'laravel', 'sincronizarDesdeApi');
    }
}
