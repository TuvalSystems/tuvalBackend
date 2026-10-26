<?php

namespace App\Http\Controllers\Inventario\Articulos;

use App\Http\Controllers\Controller;
use App\Http\Requests\Venta\Consultas\BusquedaArticuloRequest;
use App\Services\Inventario\Articulo\Contracts\ArticleSegmentConfigServiceInterface;
use App\Services\Inventario\Articulo\Contracts\ArticleServiceInterface;
use App\Services\Inventario\Articulo\Contracts\ArticleSyncServiceInterface;
use App\Traits\ExecuteApiTransactions;
use App\Traits\JsonResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ArticuloController extends Controller
{
    use JsonResponseTrait, ExecuteApiTransactions;
    private const LOG_CHANNEL = 'cotizacion';

    public function __construct(
        protected readonly ArticleSyncServiceInterface $service,
        protected readonly ArticleSegmentConfigServiceInterface $serviceConfig,
        protected readonly ArticleServiceInterface $articleService
    ) {}


    public function listaArticulos(Request $request): JsonResponse
    {
        return $this->handleApiAction(function () use ($request): JsonResponse {
            $filtros = $request->input('filters',  []);
            $lista = $this->articleService->listArticles($filtros);
            return $this->successResponse('Información Obtenida Correctamente.!', $lista);
        }, self::LOG_CHANNEL, 'listaArticulos');
    }

    public function actualizarServicioLogistico(Request $request): JsonResponse
    {
        return $this->handleApiAction(function () use ($request): JsonResponse {
            $id     = $request->input('id');
            $user   = $request->user();
            $this->articleService->updateLogisticService($id, $user);
            return $this->buildSuccessResponse('Registro actualizado correctamente.!');
        }, self::LOG_CHANNEL, 'actualizarServicioLogistico');
    }

    public function sincronizarDesdeApi(Request $request): JsonResponse
    {
        return $this->handleApiAction(function () use ($request): JsonResponse {
            $payload = $request->all();
            $resp = $this->service->createOrUpdateArticle($payload);
            return $this->successResponse('Articulo creado o actualizado con exito.', $resp, 200);
        }, self::LOG_CHANNEL, 'sincronizarDesdeApi');
    }

    public function obtenerConfiguracionColorSegmento(): JsonResponse
    {
        return  $this->handleApiAction(function (): JsonResponse {
            $resp = $this->service->getConfigColorsWithSegment();
            return $this->successResponse('Articulo creado o actualizado con exito.', $resp, 200);
        }, self::LOG_CHANNEL, 'sincronizarDesdeApi');
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
        }, self::LOG_CHANNEL, 'obtenerClasificacionSegmentacion');
    }


    public function obtenerListaItemsFiltroSegmentacion(Request $request)
    {
        return $this->handleApiAction(function () use ($request) {
            $validate = $request->validate([
                'categoria' => 'required|string',
                'clase'     => 'required|string',
                'linea'     => 'required|string'
            ]);
            $categoria  = data_get($validate, 'categoria');
            $clase      = data_get($validate, 'clase');
            $linea      = data_get($validate, 'linea');
            $lista      = $this->articleService->searchItemsByClasification($categoria, $linea, $clase);
            return $this->successResponse('Lista Obtenida correctamente.', $lista);
        }, self::LOG_CHANNEL, 'obtenerListaItemsFiltroSegmentacion');
    }


    public function buscarArticuloWeb(BusquedaArticuloRequest $request): JsonResponse
    {
        return $this->handleApiAction(function () use ($request) {
            $data = $this->articleService->searchArticlesWithDetails($request->validated());
            return $this->successResponse('Articulos obtenidos correctamente', $data, 200);
        }, self::LOG_CHANNEL, 'buscarArticuloWeb');
    }


    public function buscarArticuloL(Request $request): JsonResponse
    {
        return $this->handleApiAction(function () use ($request): JsonResponse {
            $items      = $request->input('items', []);
            $porcentaje = $request->input('porcentaje');
            $articulo   = $this->articleService->findAvailableTypeLArticle($items, $porcentaje);
            return $this->successResponse('Información Obtenida correctamente.!', $articulo);
        }, self::LOG_CHANNEL, 'buscarArticuloL');
    }


    public function obtenerArticuloLogistico(): JsonResponse
    {
        return $this->handleApiAction(function (): JsonResponse {
            $item = $this->articleService->getLogisticServiceArticle();
            return $this->successResponse('Item logístico obtenido correctamente', $item);
        }, self::LOG_CHANNEL, 'obtenerArticuloLogistico');
    }
}
