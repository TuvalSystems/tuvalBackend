<?php

namespace App\Http\Controllers\Inventario\Articulo;

use App\Http\Controllers\Controller;
use App\Services\Inventario\Articulo\Contracts\ArticleSegmentConfigServiceInterface;
use App\Services\Inventario\Articulo\Contracts\ArticleSyncServiceInterface;
use App\Traits\ExecuteApiTransactions;
use App\Traits\JsonResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ArticuloController extends Controller
{
    use JsonResponseTrait, ExecuteApiTransactions;
    public function __construct(
        protected readonly ArticleSyncServiceInterface $service,
        protected readonly ArticleSegmentConfigServiceInterface $serviceConfig
    ) {}

    public function sincronizarDesdeApi(Request $request): JsonResponse
    {
        return $this->handleApiAction(function () use ($request): JsonResponse {
            $payload = $request->all();
            $resp = $this->service->createOrUpdateArticle($payload);
            return $this->successResponse('Articulo creado o actualizado con exito.', $resp, 200);
        }, 'laravel', 'sincronizarDesdeApi');
    }

    public function obtenerConfiguracionColorSegmento(): JsonResponse
    {
        return  $this->handleApiAction(function (): JsonResponse {
            $resp = $this->service->getConfigColorsWithSegment();
            return $this->successResponse('Articulo creado o actualizado con exito.', $resp, 200);
        }, 'laravel', 'sincronizarDesdeApi');
    }


    public function obtenerConfiguracionClasificacionSegmento(Request $request)
    {
        return $this->handleApiAction(function () use ($request) {
            $payload = $request->only([
                'codigoSegmento',
                'codigoCategoria',
                'codigoLinea',
            ]);
            $resp       = $this->serviceConfig->getSegmentClassificationConfig($payload);
            return $this->successResponse('Datos Obtenido Correctamente', $resp, 200);
        }, 'laravel', 'obtenerClasificacionSegmentacion');
    }
}
