<?php

namespace App\Services\Venta;

use App\Services\Api\ApiGpService;
use App\Services\Funciones\Contracts\FunctionsServiceInterface;
use App\Services\Reposiciones\Contracts\StockWarehouseServiceInterface;
use App\Services\Venta\Contracts\InventoryQueryServiceInterface;
use App\Traits\JsonResponseTrait;

class InventoryQueryService implements InventoryQueryServiceInterface
{

    use JsonResponseTrait;
    protected $stockWarehouseService;
    protected $apiGp;
    protected $functionsService;
    public function __construct(StockWarehouseServiceInterface $stockWarehouseService, ApiGpService $apiGp, FunctionsServiceInterface $functionsService)
    {
        $this->stockWarehouseService = $stockWarehouseService;
        $this->apiGp = $apiGp;
        $this->functionsService = $functionsService;
    }



    public function getConsultStock($data)
    {
        $articulo = $data['articulo'];
        $warehouses = $this->stockWarehouseService->getWarehouseNotConsult($data);
        if (empty($warehouses)) {
            return [];
        }

        $stockRequests = array_map(fn($whr) => ['articulo' => $articulo['ART_CODIGO'], 'bodega' => $whr,], $warehouses);
        $stockResults = array_map(fn($params) => $this->apiGp->getStockWarehouse($params), $stockRequests);
        $formatStock = $this->stockWarehouseService->formatResultStocks($stockResults);
        $art = $this->stockWarehouseService->newFormatStock($formatStock, $articulo); //nose porque hace pero va
        return response([
            'success' => true,
            'stock' => $art['stock'],
            'ART_STOCK' => $art['ART_STOCK'],
        ]);
    }


    public function getConsultStockOthers($data)
    {
        $articulo = $data['articulo'];
        $warehouses = $this->stockWarehouseService->getWarehouse($data);
        if (empty($warehouses)) {
            return [];
        }

        $stockRequests = array_map(fn($whr) => ['articulo' => $articulo['ART_CODIGO'], 'bodega' => $whr,], $warehouses);
        $stockResults = array_map(fn($params) => $this->apiGp->getStockWarehouse($params), $stockRequests);
        $formatStock = $this->stockWarehouseService->formatResultStocks($stockResults);
        $art = $this->stockWarehouseService->newFormatStock($formatStock, $articulo); //nose porque hace pero va
        return response([
            'success' => true,
            'stock' => $art['stock'],
            'ART_STOCK' => $art['ART_STOCK'],
        ]);
    }



    public function getConsultStockOrder($data)
    {

        $alert = $this->functionsService->consultStockDetail($data, $data['cotizacion']);

        if (!$alert) {
            return response(['success' => true, 'message' => "Si hay stock disponible para generar el Pedido"]);
        }
        return response([
            'success' => false,
            'message' => $alert,
            'cotizacion' => $data['cotizacion'],
        ]);

    }
}
