<?php

namespace App\Services\Venta;

use App\Models\despachos\GpGuiaRemisionDetalle;
use App\Models\GpCliente;
use App\Models\GpRelacionPuntoVentaBodega;
use App\Services\Api\ApiGpService;
use App\Services\Funciones\Contracts\FunctionsServiceInterface;
use App\Services\Reposiciones\Contracts\StockWarehouseServiceInterface;
use App\Services\SqlServer\Ventas\Contracts\ClientServiceInterface;
use App\Services\Venta\Contracts\InventoryQueryServiceInterface;
use App\Traits\JsonResponseTrait;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InventoryQueryService implements InventoryQueryServiceInterface
{

    use JsonResponseTrait;
    protected $stockWarehouseService;
    protected $apiGp;
    protected $functionsService;
    protected $clientSqlService;
    public function __construct(
        StockWarehouseServiceInterface $stockWarehouseService,
        ApiGpService $apiGp,
        FunctionsServiceInterface $functionsService,
        ClientServiceInterface $clientSqlService,
    ) {
        $this->stockWarehouseService = $stockWarehouseService;
        $this->apiGp = $apiGp;
        $this->functionsService = $functionsService;
        $this->clientSqlService = $clientSqlService;
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
            $stockResults = $this->stockWarehouseService->fetchStock($stockRequests);
            $formatStock = $this->stockWarehouseService->transformStockResponse($stockResults);
            $art = $this->stockWarehouseService->computeAvailableStockByWarehouseForArticle($formatStock, $articulo); //nose porque hace pero va
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
            $stockResults = $this->stockWarehouseService->fetchStock($stockRequests);
            $formatStock = $this->stockWarehouseService->transformStockResponse($stockResults);
            $art = $this->stockWarehouseService->computeAvailableStockByWarehouseForArticle($formatStock, $articulo); //nose porque hace pero va
            return response([
                'success' => true,
                'stock' => $art['stock'],
                'ART_STOCK' => $art['ART_STOCK'],
            ]);
        } catch (\Throwable $e) {
            Log::error('Error al consultar Stock (getConsultStockOthers)', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'data' => $data,
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
            $segmento = $this->getSegmentoCliente($ruc);
            $cupoDisponible = $this->functionsService->getClientCheckAvailableQuota($ruc);

            $cliente->suspendido = (float) $cupoDisponible['suspendido'] ?? 0;
            $cliente->cupo_disponible = number_format($cupoDisponible['cupoDisponibleGp'], 2, '.', ',');
            $cliente->cupo_asignado = number_format($cupoDisponible['cupoAsignado'], 2, '.', ',');
            return response([
                'success' => true,
                'cliente' => $cliente,
                'segmento' => $segmento
            ]);
        } catch (\Throwable $e) {
            return $this->buildErrorResponse($e->getMessage(), 500);
        }
    }

    public function getSegmentoCliente($ruc)
    {
        $response = $this->apiGp->fetchClientSegment([
            'ruc' => $ruc,
        ]);
        $data = data_get($response, 'data', []);
        return is_array($data) ? $data : (array) $data;
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
    // public function getConsultItemTransito($data): array
    // {
    //     $articuloCodigo = data_get($data, 'articulo.ART_CODIGO');
    //     $warehouses = $this->stockWarehouseService->getWarehouseNotConsult($data);
    //     if (empty($warehouses)) {
    //         throw new \InvalidArgumentException('No se encontraron bodegas para consultar en transito.');
    //     }
    //     $rows = DB::table('gp_guia_remision_detalles as d')
    //         ->join('gp_guia_remisions as g', 'g.id', '=', 'd.guia_remision_id')
    //         ->where('d.GUI_REM_CODI_INTERNO',  $articuloCodigo)
    //         ->where('g.tipo', 'reposicion')
    //         ->where('g.estado', 'enviada')
    //         ->whereIn('g.bodega_destino', $warehouses)
    //         ->where(function ($query) {
    //             $query->where(function ($q2) {
    //                 $q2->where('g.bodega_destino', 'INMACONSA')
    //                     ->where('g.bodega_origen', 'IMPORT');
    //             })
    //                 ->orWhere(function ($q3) {
    //                     $q3->where('g.bodega_destino', '<>', 'INMACONSA');
    //                 });
    //         })
    //         ->groupBy('g.bodega_destino')
    //         ->select([
    //             DB::raw('g.bodega_destino as bodega'),
    //             DB::raw('COALESCE(SUM(COALESCE(d.GUI_REM_CANTIDAD,0) - COALESCE(d.recibido,0)),0) as pendiente'),
    //         ])
    //         ->pluck('pendiente', 'bodega')
    //         ->all();

    //     $items = array_map(function (string $bodega) use ($rows) {
    //         $pendiente =  (float)($rows[$bodega] ?? 0);
    //         return ['bodega' => $bodega, 'pendiente' => max(0, $pendiente)];
    //     }, $warehouses);

    //     return ['transito' => $items];
    // }


    public function getConsultItemTransito($data): array
    {
        $articuloCodigo = data_get($data, 'articulo.ART_CODIGO');
        $ptoVentaId = data_get($data, 'puntodeventa_id');

        $bodegaDestino = GpRelacionPuntoVentaBodega::query()
            ->where('PTO_VTA_BOD_PUNTO_VENTA', $ptoVentaId)
            ->value('PTO_VTA_BOD_BODEGA');

        $items = GpGuiaRemisionDetalle::where('GUI_REM_CODI_INTERNO', $articuloCodigo)
            ->whereHas('guia', function ($query) use ($bodegaDestino) {
                $query->where('tipo', 'reposicion')
                    ->where('estado', 'enviada')
                    ->where('bodega_destino', $bodegaDestino);
            })
            ->value(DB::raw('COALESCE(SUM(COALESCE(GUI_REM_CANTIDAD,0) - (COALESCE(recibido,0))), 0)'));
        return [
            'bodega' => $bodegaDestino,
            'pendiente' => max(0, $items)
        ];
    }



    public function fetchStockInTransit()
    {
        return DB::table('gp_guia_remision_detalles as d')
            ->join('gp_guia_remisions as g', 'g.id', '=', 'd.guia_remision_id')
            ->where('g.tipo', 'reposicion')
            ->where('g.estado', 'enviada')
            ->select([
                DB::raw('g.bodega_destino as bodega'),
                DB::raw('d.GUI_REM_CODI_INTERNO as codigo'),
                DB::raw('COALESCE(SUM(COALESCE(d.GUI_REM_CANTIDAD,0) - COALESCE(d.recibido,0)),0) as pendiente'),
            ])
            ->groupBy('g.bodega_destino', 'd.GUI_REM_CODI_INTERNO')
            ->having('pendiente', '>', 0)
            ->orderBy('bodega')
            ->orderBy('codigo')
            ->get();
    }



    public function listReservedInventory()
    {
        $baseOrders = fn() => DB::table('gp_pedido_detalle_stocks as st')
            ->join('gp_pedido_detalles as d', 'd.id', 'st.detalle_id')
            ->join('gp_pedidos as p', 'p.id', '=', 'st.pedido_id')
            ->where('st.reservado', '>', 0)
            ->whereNotIn('p.estado', ['eliminado', 'rechazado', 'anulada'])
            ->where(function ($q) {
                $q->whereNull('p.despachado')->orWhere('p.despachado', 0);
            })
            ->selectRaw(
                "
            d.ART_CODIGO as codigo,
            st.STO_ART_BODEGA AS bodega,
            st.reservado AS reservado,
            'pedido' as tipo"
            );

        //caso a : no requiere comercial, no requiere crediticia
        $pA = (clone $baseOrders())
            ->where('p.requiere_aprobacion_comercial', 'no')
            ->where('p.requiere_aprobacion_crediticia', 'no');

        //Caso B: no requiere comercial, requiere crediticia y está aprobada
        $pB = (clone $baseOrders())
            ->where('p.requiere_aprobacion_comercial', 'no')
            ->where('p.requiere_aprobacion_crediticia', 'yes')
            ->where('p.aprobado_crediticio', 'yes');

        // Caso C: requiere comercial y aprobada, no requiere crediticia
        $pC = (clone $baseOrders())
            ->where('p.requiere_aprobacion_comercial', 'yes')
            ->where('p.aprobado_comercial', 'yes')
            ->where('p.requiere_aprobacion_crediticia', 'no');

        // Caso D: requiere ambas y ambas aprobadas
        $pD = (clone $baseOrders())
            ->where('p.requiere_aprobacion_comercial', 'yes')
            ->where('p.aprobado_comercial', 'yes')
            ->where('p.requiere_aprobacion_crediticia', 'yes')
            ->where('p.aprobado_crediticio', 'yes');
        $unionOrders = $pA->unionAll($pB)->unionAll($pC)->unionAll($pD);

        $traslados = DB::table('gp_traslados_reposicions as t')
            ->join('gp_articulos as a', 'a.ART_CODIGO', '=', 't.ART_CODIGO')
            ->where(function ($q) {
                $q->whereNull('t.completado')->orWhere('t.completado', 0);
            })
            ->selectRaw(
                "
            t.ART_CODIGO as codigo,
            t.bodega_destino AS bodega,
            t.cantidad AS reservado,
            'traslado' as tipo"
            );

        // ----- UNION final y ordenamiento -----

        $unionAll = DB::query()->fromSub($unionOrders, 'ped')
            ->unionAll($traslados);
        $response = DB::query()->fromSub($unionAll, 'u')
            ->where('u.reservado', '>', 0)
            ->orderBy('u.codigo')
            ->orderBy('u.bodega')
            ->get()
            ->map(function ($item) {
                $item->reservado = (float) $item->reservado;
                return $item;
            });
        $response2 = $this->listReservedInventoryByPendingUse();
        $response3 = $this->listReservedInventoryByTransfers();
        return collect($response)
            ->concat($response2)
            ->concat($response3)
            ->values();
    }

    public function listReservedInventoryByPendingUse()
    {
        $baseQuotation = DB::table('gp_cotizacion_detalle_stocks as st')
            ->join('gp_cotizacion_detalles as d', 'd.id', 'st.detalle_id')
            ->join('gp_cotizacions as p', 'p.id', '=', 'st.cotizacion_id')
            ->join('gp_reservas as r', 'r.cotizacion_id', '=', 'p.id')
            ->where('st.seleccionado', '>', 0)
            ->where('st.cantidad_traslado' ,'=', 0)
            ->whereIn('r.estado', ['aprobado', 'traslado'])
            ->selectRaw(
                "
            d.ART_CODIGO as codigo,
            st.STO_ART_BODEGA AS bodega,
            st.seleccionado AS reservado,
            p.cotizacion as cotizacion,
            'reserva' as tipo",

            );
        return DB::query()->fromSub($baseQuotation, 'u')
            ->where('u.reservado', '>', 0)
            ->orderBy('u.codigo')
            ->orderBy('u.bodega')
            ->get()
            ->map(function ($item) {
                $item->reservado = (float) $item->reservado;
                return $item;
            });
    }

    public function listReservedInventoryByTransfers()
    {
        $baseQuotation = DB::table('gp_cotizacion_detalle_stocks as st')
            ->join('gp_cotizacion_detalles as d', 'd.id', 'st.detalle_id')
            ->join('gp_cotizacions as p', 'p.id', '=', 'st.cotizacion_id')
            ->join('gp_reservas as r', 'r.cotizacion_id', '=', 'p.id')
            ->where('st.cantidad_traslado', '>', 0)
            ->whereIn('r.estado', ['traslado'])
            ->selectRaw(
                "
            d.ART_CODIGO as codigo,
            st.STO_ART_BODEGA AS bodega,
            st.cantidad_traslado AS reservado,
            p.cotizacion as cotizacion,
            'traslado_reservas' as tipo",
            );
        return DB::query()->fromSub($baseQuotation, 'u')
            ->where('u.reservado', '>', 0)
            ->orderBy('u.codigo')
            ->orderBy('u.bodega')
            ->get()
            ->map(function ($item) {
                $item->reservado = (float) $item->reservado;
                return $item;
            });
    }
}
