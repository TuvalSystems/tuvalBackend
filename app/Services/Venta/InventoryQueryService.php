<?php

namespace App\Services\Venta;

use App\Models\despachos\GpGuiaRemisionDetalle;
use App\Models\GpCliente;
use App\Models\GpPuntoVenta;
use App\Models\GpRelacionPuntoVentaBodega;
use App\Services\Api\ApiGpService;
use App\Services\Funciones\Contracts\FunctionsServiceInterface;
use App\Services\Reposiciones\Contracts\StockWarehouseServiceInterface;
use App\Services\SqlServer\Bodega\Contracts\StockSqlServiceInterface;
use App\Services\SqlServer\Cobranza\Contracts\CreditValidationServiceInterface;
use App\Services\SqlServer\Ventas\Contracts\ClientServiceInterface;
use App\Services\Venta\Contracts\InventoryQueryServiceInterface;
use App\Traits\JsonResponseTrait;
use Illuminate\Support\Facades\Log;

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
        try {
            $articulo = $data['articulo'];
            $warehouses = $this->stockWarehouseService->getWarehouseNotConsult($data);
            if (empty($warehouses)) {
                return [];
            }

            $stockRequests = array_map(fn($whr) => ['articulo' => $articulo['ART_CODIGO'], 'bodega' => $whr,], $warehouses);
            $stockResults = array_map(fn($params) =>  $this->stockSqlService->consultStockWarehouseSql($params), $stockRequests);
            $art = $this->stockWarehouseService->computeAvailableStockByWarehouseForArticle($stockResults, $articulo); //nose porque hace pero va
            return response([
                'success' => true,
                'stock' => $art['stock'],
                'ART_STOCK' => $art['ART_STOCK'],
            ]);
        } catch (\Throwable $e) {
            Log::error('Error al consultar Stock (getConsultStock)' . ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString(), 'file' => $e->getFile(), 'line' => $e->getLine(), 'data' => $data]);
            return $this->buildErrorResponse('Stock insuficiente', 500);
        }
    }


    public function getConsultStockOthers($data)
    {
        try {
            $articulo = $data['articulo'];
            $warehouses = $this->stockWarehouseService->getWarehouse($data);
            if (empty($warehouses)) {
                return [];
            }

            $stockRequests = array_map(fn($whr) => ['articulo' => $articulo['ART_CODIGO'], 'bodega' => $whr,], $warehouses);
            $stockResults = array_map(fn($params) =>  $this->stockSqlService->consultStockWarehouseSql($params), $stockRequests);
            $art = $this->stockWarehouseService->computeAvailableStockByWarehouseForArticle($stockResults, $articulo); //nose porque hace pero va
            return response([
                'success' => true,
                'stock' => $art['stock'],
                'ART_STOCK' => $art['ART_STOCK'],
            ]);
        } catch (\Throwable $e) {
            Log::error('Error al consultar Stock (getConsultStockOthers)', [
                'error' => $e->getMessage(),
                'file'  => $e->getFile(),
                'line'  => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'data'  => $data,
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Stock insuficiente'
            ], 500);
        }
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



    /**
     *  consulta de items en transito por reposicion
     * estado : pendiente
     * tipo de guia : reposicion
     * @articulo
     * @punto_venta
     * @param mixed $data
     * @return void
     */
    public function getConsultItemTransito($data): array
    {
        $articuloCodigo = data_get($data, 'articulo.ART_CODIGO');
        $ptoVentaId     = data_get($data, 'puntodeventa_id');

        $bodegaDestino  = GpRelacionPuntoVentaBodega::query()
            ->where('PTO_VTA_BOD_PUNTO_VENTA', $ptoVentaId)
            ->value('PTO_VTA_BOD_BODEGA');

        $items = GpGuiaRemisionDetalle::where('GUI_REM_CODI_INTERNO', $articuloCodigo)
            ->whereHas('guia', function ($query) use ($bodegaDestino) {
                $query->where('tipo', 'reposicion')
                    ->where('estado', 'enviada')
                    ->where('bodega_destino', $bodegaDestino);
            })
            ->sum('GUI_REM_CANTIDAD');
        return [
            'bodega_destino' => $bodegaDestino,
            'transito' => (int) $items
        ];
    }
}
