<?php

namespace App\Services\Builders\Bodega;

use App\Models\despachos\GpGuiaRemision;
use App\Services\Builders\Bodega\Contracts\DeliveryNoteParamsBuilderInterface;

class DeliveryNoteParamsBuilder implements DeliveryNoteParamsBuilderInterface
{

    public function buildParamsHeaderTransfer(GpGuiaRemision $guia, string $documento, array $items, $user): array
    {
        $header = [
            'idCompany' => config('services.api_gp.company'),
            'pedido' => $documento,
            'bodegaOrigen' => $guia->bodega_origen,
            'bodegaDestino' => $guia->bodega_destino,
            'observacion' => $guia->GUI_REM_OBSERVACION,
            'usuario' => $user->USERID ?? 'adminWeb',
            'numTranferencia' => '',
            'idGuiaRemision' => $guia->id,
            'guiaRemision' => $guia->GUI_REM_GUIA_REMISION
        ];
        return [
            'cabecera' => $header,
            'detalle' => $items
        ];
    }


    public function buildParameterHeaderAndDetailDispatchShipment(GpGuiaRemision $guia, string $documento, string $newOrder, array $items, $user): array
    {
        $header = [
            'idCompany' => config('services.api_gp.company'),
            'pedido' => $documento,
            'pedidoNuevo' => $newOrder,
            'bodegaOrigen' => $guia->bodega_origen,
            'observacion' => $guia->GUI_REM_OBSERVACION,
            'usuario' => $user->USERID ?? 'adminWeb',
            'idGuiaRemision' => $guia->id,
            'guiaRemision' => $guia->GUI_REM_GUIA_REMISION
        ];
        return [
            'cabecera' => $header,
            'detalle' => $items
        ];
    }


    /** api para proceso de reajuste de transferencia */

    public function buildingTransferGuideAdjustmentPayload($params)
    {
        $payloadHeader = [
            'orderNumber' => data_get($params, 'pedido'),
            'newOrderNumber' => "",
            'origen' => data_get($params, 'bodegaOrigen'),
            'idGuiaRemision' => data_get($params, 'idGuiaRemision'),
            'guiaRemision' => data_get($params, 'guiaRemision'),
        ];
        return $payloadHeader;
    }


    public function buildingRepositionGuideAdjustmentPayload($params)
    {
        return  [
            'orderNumber' => data_get($params, 'pedido'),
            'origen' => data_get($params, 'bodegaOrigen'),
            'idMovimiento' => "",
            'idGuiaRemision' => data_get($params, 'idGuiaRemision'),
            'guiaRemision' => data_get($params, 'guiaRemision'),

        ];
    }


    public function buildingDispatchGuideAdjustmentPayload($params)
    {
        return [
            'orderNumber' => data_get($params, 'pedido'),
            'newOrderNumber' => data_get($params, 'pedidoNuevo' ?? ''),
            'idGuiaRemision' => data_get($params, 'idGuiaRemision'),
            'guiaRemision' => data_get($params, 'guiaRemision'),
        ];
    }
}
