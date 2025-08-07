<?php

namespace App\Services\Venta;

use App\Models\GpCliente;
use App\Services\Api\ApiGpService;
use App\Services\Funciones\Contracts\FunctionsServiceInterface;
use App\Services\Reposiciones\Contracts\StockWarehouseServiceInterface;
use App\Services\SqlServer\Bodega\Contracts\StockSqlServiceInterface;
use App\Services\SqlServer\Cobranza\Contracts\CreditValidationServiceInterface;
use App\Services\SqlServer\Ventas\Contracts\ClientServiceInterface;
use App\Services\Venta\Contracts\InventoryQueryServiceInterface;
use App\Traits\JsonResponseTrait;

class InventoryQueryService implements InventoryQueryServiceInterface
{

    use JsonResponseTrait;
    protected $stockWarehouseService;
    protected $apiGp;
    protected $functionsService;
    protected $sqlCreditValidtionService;
    protected $clientSqlService;
    protected $stockSqlService;
    public function __construct(
        StockWarehouseServiceInterface $stockWarehouseService,
        ApiGpService $apiGp,
        FunctionsServiceInterface $functionsService,
        CreditValidationServiceInterface $sqlCreditValidtionService,
        ClientServiceInterface $clientSqlService,
        StockSqlServiceInterface $stockSqlService
    ) {
        $this->stockWarehouseService = $stockWarehouseService;
        $this->apiGp = $apiGp;
        $this->functionsService = $functionsService;
        $this->sqlCreditValidtionService = $sqlCreditValidtionService;
        $this->clientSqlService = $clientSqlService;
        $this->stockSqlService = $stockSqlService;
    }



    public function getConsultStock($data)
    {
        $articulo = $data['articulo'];
        $warehouses = $this->stockWarehouseService->getWarehouseNotConsult($data);
        if (empty($warehouses)) {
            return [];
        }

        $stockRequests = array_map(fn($whr) => ['articulo' => $articulo['ART_CODIGO'], 'bodega' => $whr,], $warehouses);
        // $stockResults = array_map(fn($params) => $this->apiGp->getStockWarehouse($params), $stockRequests);
        $stockResults = array_map(fn($params) =>  $this->stockSqlService->consultStockWarehouseSql($params), $stockRequests);
        // $formatStock = $this->stockWarehouseService->formatResultStocks($stockResults);
        $art = $this->stockWarehouseService->newFormatStock($stockResults, $articulo); //nose porque hace pero va
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

    public function getAvailableSpace($ruc)
    {

        try {
            $cliente = GpCliente::where('CLI_RUC', $ruc)->first();
            $cupoDisponible =  $this->sqlCreditValidtionService->consultCreditValidate($ruc, 3);
            $cupo = $cupoDisponible->first();
            $cliente->suspendido = (float)$cupo->SUSPENDIDO ?? 0;
            $cliente->cupo_disponible = number_format($cupo->CUPO_PERMITIDO, 2, '.', ',');
            $cliente->cupo_asignado = number_format($cupo->CUPO, 2, '.', ',');
            return response([
                'success' => true,
                'cliente' => $cliente
            ]);
        } catch (\Throwable $e) {
            return $this->buildErrorResponse($e->getMessage(), 500);
        }
    }

    public function getConsultClientSegment($ruc)
    {
        $segmento = $this->clientSqlService->validateClientforRucSegment($ruc);

        if (is_array($segmento)) {
            return $this->buildSuccessResponse('Segmento encontrado', 200);
        } else {
            return $this->buildErrorResponse('Segmento no encontrado', 500);
        }
    }
}
