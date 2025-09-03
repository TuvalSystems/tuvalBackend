<?php

namespace App\Services\Builders\Bodega;

use App\Models\despachos\GpGuiaRemision;
use App\Services\Builders\Bodega\Contracts\DeliveryNoteParamsBuilderInterface;

class DeliveryNoteParamsBuilder implements DeliveryNoteParamsBuilderInterface
{

    public function buildParamsHeaderTransfer(GpGuiaRemision $guia, string $documento, array $items,  $user): array
    {
        $header = [
            'idCompany' => config('services.api_gp.company'),
            'pedido' => $documento,
            'bodegaOrigen' => $guia->bodega_origen,
            'bodegaDestino' => $guia->bodega_destino,
            'observacion' => $guia->GUI_REM_OBSERVACION,
            'usuario' => $user->USERID ?? 'adminWeb',
        ];
        return [
            'cabecera' => $header,
            'detalle' => $items
        ];
    }


    public function buildParameterHeaderAndDetailDispatchShipment(GpGuiaRemision $guia, string $documento, string $newOrder, array $items, $user): array{
        $header = [
            'idCompany' => config('services.api_gp.company'),
            'pedido' => $documento,
            'pedidoNuevo' => $newOrder,
            'bodegaOrigen' => $guia->bodega_origen,
            'observacion' => $guia->GUI_REM_OBSERVACION,
            'usuario' => $user->USERID ?? 'adminWeb',
        ];
        return [
            'cabecera' => $header,
            'detalle' => $items
        ];
    }
}
