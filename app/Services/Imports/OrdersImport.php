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
        $ordersSql = $this->getAllOrdersSqlPendingUpdate($fechaIni, $fechaFin);
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


    protected function getAllOrdersSqlPendingUpdate(string $fechaIni, string $fechaFin)
    {
        $resp = $this->api->fetchAllOrdersPendingSql([
            'fechaInicio' => $fechaIni,
            'fechaFin' => $fechaFin
        ]);

        return data_get($resp, 'data', []);
    }

    protected function getAllOrdersDetailSqlPendingUpdate(array $keyOrders)
    {
        $resp = $this->api->fetchAllOrdersDetailPendingSql([
            'pedidos' => $keyOrders
        ]);
        return data_get($resp, 'data', []);
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
