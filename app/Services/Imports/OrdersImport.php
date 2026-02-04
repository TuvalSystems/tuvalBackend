<?php

namespace App\Services\Imports;

use App\Models\GpPedido;
use App\Models\GpPedidoDetalle;
use App\Services\Api\ApiGpService;
use App\Services\Imports\Contracts\OrdersImportInterface;

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




        $detailsSql = $this->getAllOrdersDetailSqlPendingUpdate($keyOrders);

        $mapaSqlDetails = $this->extractOrdersDetails($detailsSql);
        //obtener todos los pedidos web por $ordersSql
        $detailOrdersWeb = GpPedidoDetalle::whereIn('pedido_id', $ordersWeb->pluck('id'))->with(['pedido:id,pedido'])->get();

        $payloadDetalles = [];
        foreach ($detailOrdersWeb as $detalleWeb) {
            $llaveWeb = trim($detalleWeb->pedido->pedido) . '-' . trim($detalleWeb->ART_CODIGO);
            if ($mapaSqlDetails->has($llaveWeb)) {
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
        if (!empty($payloadCabeceras)) {
            $this->api->updateAllOrdersPendingSql($payloadCabeceras);
        }
        if (!empty($payloadDetalles)) {
            $this->api->updateAllOrdersDetailPendingSql($payloadDetalles);
        }
        return [
            'cabeceras_procesadas' => count($payloadCabeceras),
            'detalles_procesados'  => count($payloadDetalles )// $payloadDetalles
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
            $key = trim($item['docOrigen']) . '-' . trim($item['artCodigo']);
            return [$key => $item];
        });
    }
}
