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

        $ordersSql = $this->getAllOrdersSqlPendingUpdate($fechaIni, $fechaFin);
        if (empty($ordersSql)) {
            return 0;
        }
        $keyOrders = $this->extractOrderIds($ordersSql);
        $ordersWeb = GpPedido::whereIn('pedido', $keyOrders)->get();

        $mapaSqlHeaders = collect($ordersSql)->keyBy(function ($item) {
            return trim($item['docOrigen']);
        });

        $payloadCabeceras = [];
        foreach ($ordersWeb as $pedidoWeb) {
            $key = trim($pedidoWeb->pedido);
            if ($mapaSqlHeaders->has($key)) {
                $dataSql = $mapaSqlHeaders->get($key);
                $payloadCabeceras[] = [
                    'dexRowId'      => $dataSql['dexRowId'],
                    'pedido'        => $pedidoWeb->pedido,
                    'userIdCreator' => $pedidoWeb->USERID ?? 'adminWeb',
                    'condIdOriginal'   => $pedidoWeb->COND_ID_ORIGINAL,
                ];
            }
        }




        $detailsSql = [];
        $chunks = array_chunk($keyOrders, 1000);
        foreach ($chunks as $LotePedidos) {
            $resultadoLote = $this->getAllOrdersDetailSqlPendingUpdate($LotePedidos);
            if (!empty($resultadoLote)) {
                $detailsSql = array_merge($detailsSql, $resultadoLote);
            }
        }

        Log::info("DEBUG DETALLES SQL: Se encontraron " . count($detailsSql) . " filas pendientes en SQL Server.");
        if (count($detailsSql) > 0) {
            // Muestra el primer item para ver cómo viene el 'artCodigo' y 'docOrigen'
            Log::info("DEBUG ITEM SQL EJEMPLO:", $detailsSql[0]);
        }

        $mapaSqlDetails = $this->extractOrdersDetails($detailsSql);
        Log::info("map keys", [$mapaSqlDetails]);

        //obtener todos los pedidos web por $ordersSql
        $detailOrdersWeb = GpPedidoDetalle::whereIn('pedido_id', $ordersWeb->pluck('id'))->with(['pedido:id,pedido'])->get();
        Log::info("DEBUG DETALLES WEB: Se encontraron " . count($detailOrdersWeb) . " filas en Laravel.");
        $payloadDetalles = [];
        $coincidencias = 0;
        foreach ($detailOrdersWeb as $detalleWeb) {
            $docWeb  = strtoupper(trim($detalleWeb->pedido->pedido));
            $artWeb  = strtoupper(trim($detalleWeb->ART_CODIGO));
            $lineaWeb = (int) $detalleWeb->orden;
            $llaveWeb = $docWeb . '-' . $artWeb . '-' . $lineaWeb;

            if ($mapaSqlDetails->has($llaveWeb)) {
                $coincidencias++;
                $datoSql = $mapaSqlDetails->get($llaveWeb);
                $payloadDetalles[] = [
                    'dexRowId'              => $datoSql['dexRowId'],
                    'bodega'                => $datoSql['bodega'],
                    'artWeb'                => $detalleWeb->ART_CODIGO,
                    'artCodigo'             => $datoSql['artCodigo'],
                    'descripcionOrginal'    => $detalleWeb->ART_DESCRIPCION_ORIGINAL,
                    'valorOrginal'          => $detalleWeb->ART_VALOR_ORIGINAL,
                    'descuentoOrginal'      => $detalleWeb->descuento_original,
                    'docGenerado'           => $datoSql['docOrigen']
                ];
            }
        }
        Log::info("DEBUG MATCH FINAL: Se encontraron {$coincidencias} coincidencias para actualizar.");
        // if (!empty($payloadCabeceras)) {
        //     foreach (array_chunk($payloadCabeceras, 1000) as $loteCabeceras) {
        //         $this->api->updateAllOrdersPendingSql($loteCabeceras);
        //     }
        // }
        if (!empty($payloadDetalles)) {
            foreach (array_chunk($payloadDetalles, 1000) as $loteDetalles) {
                Log::info("Detalle enviado  a actualizar:", $loteDetalles);

                $this->api->updateAllOrdersDetailPendingSql($loteDetalles);
            }
        }
        return [
            'cabeceras_procesadas' => count($payloadCabeceras),
            'detalles_procesados'  => count($payloadDetalles) // $payloadDetalles
        ];
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
}
