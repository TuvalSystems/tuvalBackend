<?php

namespace App\Services\Imports;

use App\Models\GpPedido;
use App\Models\GpPedidoDetalle;
use App\Services\Api\ApiGpService;
use App\Services\Imports\Contracts\OrdersImportInterface;
use Illuminate\Support\Facades\Log;

class OrdersImport implements OrdersImportInterface
{

    public function __construct(protected readonly ApiGpService $api) {}


    public function updateDataSqlServerOrdersAndDetail(string $fechaIni, string $fechaFin)
    {
        Log::info("Iniciando sincronizacion de pedidos", [
            'fechaIni' => $fechaIni,
            'fechaFin' => $fechaFin
        ]);
        // $ordersSql = $this->getAllOrdersSqlPendingUpdate($fechaIni, $fechaFin);
        if (empty($ordersSql)) {
            return 0;
        }
        Log::info("SQL ejecutado: ", [
            'ordersSql' => $ordersSql
        ]);
        Log::info("Registros a procesar: " . count($ordersSql));
        $keyOrders = $this->extractOrderIds($ordersSql);
        $chunks = array_chunk($keyOrders, 500);


        $totHeaders = 0;
        $totalDetails = 0;

        foreach ($chunks as $lotesIds) {
            Log::info("Procesando lotes de :", [
                'lotes' => $lotesIds
            ]);
            $ordersWeb = GpPedido::whereIn('pedido', $lotesIds)->get();
            $ordersIdsWeb = $ordersWeb->pluck('id');
            Log::info("Registros a procesar: ", [
                'ordersIdsWeb' => $ordersIdsWeb
            ]);
            $payloadHeaders = $this->buildPaylaodHeader($ordersWeb, $ordersSql);
            if (!empty($payloadHeaders)) {
                $this->api->updateAllOrdersPendingSql($payloadHeaders);
                $totHeaders += count($payloadHeaders);
            }


            $resultadoLoteSql = $this->getAllOrdersDetailSqlPendingUpdate($lotesIds);
            if (!empty($resultadoLoteSql)) {
                $mapaSqlDetails = $this->extractOrdersDetails($resultadoLoteSql);

                $detailOrdersWeb = GpPedidoDetalle::whereIn('pedido_id', $ordersIdsWeb)
                    ->with(['pedido:id,pedido'])
                    ->get();

                $payloadDetails = $this->buildPayloadDetalles($detailOrdersWeb, $mapaSqlDetails);
                if (!empty($payloadDetails)) {
                    $this->api->updateAllOrdersDetailPendingSql($payloadDetails);
                    $totalDetails += count($payloadDetails);
                }
            }
            unset($ordersWeb, $detailOrdersWeb, $mapaSqlDetails, $payloadDetalles);
        }
        Log::channel('single')->info("MIGRACIÓN COMPLETADA: Periodo {$fechaIni} - {$fechaFin}", [
            'cabeceras' => $totHeaders,
            'detalles' => $totalDetails,
            'memoria_peak' => (memory_get_peak_usage(true) / 1024 / 1024) . ' MB'
        ]);
    }


    protected function getAllOrdersSqlPendingUpdate(array $payload)
    {
        $resp = $this->api->fetchAllOrdersPendingSql($payload);
        return data_get($resp, 'data', []);
    }

    protected function getAllOrdersDetailSqlPendingUpdate(array $payload)
    {
        $resp = $this->api->fetchAllOrdersDetailPendingSql([
            'detalles' => $payload
        ]);
        return data_get($resp, 'data', []);
    }





    public function getOrdersByUpdate(string $start, string $end)
    {
        return GpPedido::query()
            ->with([
                'usuarioComercial:id,USERID'
            ])
            ->select([
                'id',
                'pedido',
                'aprobador_comercial_id'
            ])
            ->where('import_job', 0)
            ->whereBetween('created_at', [$start, $end])
            ->orderBy('id');
    }

    public function getOrdersDetailByUpdate(string $start, string $end)
    {
        return GpPedidoDetalle::query()
            ->with([
                'pedido:id,pedido'
            ])
            ->select([
                'id',
                'pedido_id',
                'orden',
                'ART_CODIGO',
                'descuento',
            ])
            ->where('import_job', 0)
            ->whereBetween('created_at', [$start, $end])
            ->orderBy('id');
    }


    public function markOrdersAsInProcess(array $ids): int
    {
        return GpPedido::whereIn('id', $ids)
            ->where('import_job', 0)
            ->update([
                'import_job' => 3,
            ]);
    }
    public function markOrderDetailsAsInProcess(array $ids): int
    {
        return GpPedidoDetalle::whereIn('id', $ids)
            ->where('import_job', 0)
            ->update([
                'import_job' => 3,
            ]);
    }

    public function updateSqlServerOrdersFromPayload(array $payload)
    {
        $orders = Collect($payload);
        $orderNumbers = $orders
            ->pluck('pedido')
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        if (empty($orderNumbers)) {
            return;
        }

        $requestPayload = [
            'pedidos' => $orderNumbers,
        ];
        $response = $this->getAllOrdersSqlPendingUpdate($requestPayload);
        $sqlOrders = collect($response)->keyBy(function ($item) {
            return data_get($item, 'docOrigen');
        });

        $updatePayload  = [];
        $ordersOk       = [];
        $ordersError    = [];

        foreach ($orders as $order) {
            $pedido = data_get($order, 'pedido');
            $sqlOrder = $sqlOrders->get($pedido);
            if (!$sqlOrder) {
                $ordersError[] = data_get($order, 'id');
                continue;
            }
            $updatePayload[] = [
                'orderId'           => data_get($order, 'id'),
                'pedido'            => $pedido,
                'dexRowId'          => data_get($sqlOrder, 'dexRowId'),
                'docGenerado'       => data_get($sqlOrder, 'docGenerado'),
                'ptoVta'            => data_get($sqlOrder, 'ptoVta'),
                'bodega'            => data_get($sqlOrder, 'bodega'),
                'userApprovalId'    => data_get($order, 'userApproval'),
            ];

            $ordersOk[] = data_get($order, 'id');
        }

        Log::info('payload_update_pedidos', [
            'payload'       => $updatePayload,
            'orders_error'  => $ordersError,
        ]);

        if (!empty($ordersError)) {
            GpPedido::whereIn('id', $ordersError)->update([
                'import_job' => 2,
            ]);
        }

        if (empty($updatePayload)) {
            return;
        }

        $respUpdate =  $this->api->updateAllOrdersPendingSql([
            'headers' => $updatePayload
        ]);
        $success = data_get($respUpdate, 'success', false);
        if (!$success) {
            GpPedido::whereIn('id', $ordersOk)->update([
                'import_job' => 2,
            ]);
        }
        GpPedido::whereIn('id', $ordersOk)->update([
            'import_job' => 1,
        ]);
    }







    public function updateSqlServerOrderDetailsFromPayload(array $payload)
    {
        $details = collect($payload);

        $requestPayload = $details
            ->map(function ($det) {
                return [
                    'pedido'    => data_get($det, 'pedido'),
                    'artCodigo' => data_get($det, 'artCodigo'),
                    'orden'     => data_get($det, 'orden'),
                ];
            })
            ->filter(function ($det) {
                return !empty(data_get($det, 'pedido'))
                    && !empty(data_get($det, 'artCodigo'))
                    && !is_null(data_get($det, 'orden'));
            })
            ->unique(function ($det) {
                return data_get($det, 'pedido') . '|' . data_get($det, 'artCodigo') . '|' . data_get($det, 'orden');
            })
            ->values()
            ->toArray();

        if (empty($requestPayload)) {
            return;
        }
        $response = $this->getAllOrdersDetailSqlPendingUpdate($requestPayload);
        $sqlDetails = collect($response)->keyBy(function ($item) {
            return data_get($item, 'docOrigen')
                . '|'
                . data_get($item, 'artCodigo')
                . '|'
                . data_get($item, 'linea');
        });

        $updatePayload = [];
        $detailsOk = [];
        $detailsError = [];

        foreach ($details as $detail) {
            $key = data_get($detail, 'pedido')
                . '|'
                . data_get($detail, 'artCodigo')
                . '|'
                . data_get($detail, 'orden');

            $sqlDetail = $sqlDetails->get($key);

            if (!$sqlDetail) {
                $detailsError[] = data_get($detail, 'id');
                continue;
            }

            $updatePayload[] = [
                'pedidoDetalleId' => data_get($detail, 'id'),
                'pedidoId'        => data_get($detail, 'pedido_id'),
                'pedido'          => data_get($detail, 'pedido'),
                'artCodigo'       => data_get($detail, 'artCodigo'),
                'orden'           => data_get($detail, 'orden'),
                'descuento'       => data_get($detail, 'descuento'),
                'dexRowId'        => data_get($sqlDetail, 'dexRowId'),
                'bodega'          => data_get($sqlDetail, 'bodega'),
            ];

            $detailsOk[] = data_get($detail, 'id');
        }
        Log::info('payload_update_detalle_pedidos', [
            'payload' => $updatePayload,
            'details_error' => $detailsError,
        ]);

        if (!empty($detailsError)) {
            GpPedidoDetalle::whereIn('id', $detailsError)->update([
                'import_job' => 2,
            ]);
        }

        if (empty($updatePayload)) {
            return;
        }

        $respUpdate =  $this->api->updateAllOrdersDetailPendingSql([
            'detalles' => $updatePayload
        ]);
        $success = data_get($respUpdate, 'success', false);
        if (!$success) {
            GpPedidoDetalle::whereIn('id', $detailsOk)->update([
                'import_job' => 2,
            ]);
        }
        GpPedidoDetalle::whereIn('id', $detailsOk)->update([
            'import_job' => 1,
        ]);
    }













    /**funciones clean code  */

    protected function extractOrderIds($ordersSql): array
    {
        return  collect($ordersSql)->pluck('docOrigen')->map(fn($id) => trim($id))->toArray();
    }

    protected function extractOrdersDetails($detailsSql)
    {
        return collect($detailsSql)->mapWithKeys(function ($item) {
            $doc = strtoupper(trim($item['docOrigen']));
            $art = strtoupper(trim($item['artCodigo']));
            $linea = isset($item['linea']) ? (int)$item['linea'] : 0;
            $key = $doc . '-' . $art . '-' . $linea;
            return [$key => $item];
        });
    }




    /** funciones helpers  */

    protected function buildPaylaodHeader($ordersWeb, $ordersSql)
    {

        $mapaSqlHeaders = collect($ordersSql)->keyBy(fn($item) => trim($item['docOrigen']));
        $payload = [];
        foreach ($ordersWeb as $pedidoWeb) {
            $key = trim($pedidoWeb->pedido);
            if ($mapaSqlHeaders->has($key)) {
                $dataSql = $mapaSqlHeaders->get($key);
                $payload[] = [
                    'dexRowId'      => $dataSql['dexRowId'],
                    'pedido'        => $pedidoWeb->pedido,
                    'userIdCreator' => $pedidoWeb->USERID ?? 'adminWeb',
                    'condIdOriginal'   => $pedidoWeb->COND_ID_ORIGINAL,
                ];
            }
        }

        return $payload;
    }

    protected function buildPayloadDetalles($detailOrdersWeb, $mapaSqlDetails)
    {
        $payload = [];
        foreach ($detailOrdersWeb as $detalleWeb) {
            $docWeb  = strtoupper(trim($detalleWeb->pedido->pedido));
            $artWeb  = strtoupper(trim($detalleWeb->ART_CODIGO));
            $lineaWeb = (int) $detalleWeb->orden;
            $llaveWeb = $docWeb . '-' . $artWeb . '-' . $lineaWeb;

            if ($mapaSqlDetails->has($llaveWeb)) {
                $datoSql = $mapaSqlDetails->get($llaveWeb);
                $payload[] = [
                    'dexRowId'           => $datoSql['dexRowId'],
                    'bodega'             => $datoSql['bodega'],
                    'artWeb'             => $detalleWeb->ART_CODIGO,
                    'artCodigo'          => $datoSql['artCodigo'],
                    'descripcionOrginal' => $detalleWeb->ART_DESCRIPCION_ORIGINAL,
                    'valorOrginal'       => $detalleWeb->ART_VALOR_ORIGINAL,
                    'descuentoOrginal'   => $detalleWeb->descuento_original,
                    'docGenerado'        => $datoSql['docOrigen']
                ];
            }
        }
        return $payload;
    }
}
