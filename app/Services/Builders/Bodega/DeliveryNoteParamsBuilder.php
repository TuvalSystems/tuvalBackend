<?php

namespace App\Services\Builders\Bodega;

use App\Services\Builders\Bodega\Contracts\DeliveryNoteParamsBuilderInterface;

class DeliveryNoteParamsBuilder implements DeliveryNoteParamsBuilderInterface
{
    public function buildingRepositionGuideAdjustmentPayload($params)
    {
        return  [
            'orderNumber'       => data_get($params, 'pedido'),
            'origen'            => data_get($params, 'bodegaOrigen'),
            'idMovimiento'      => "",
            'idGuiaRemision'    => data_get($params, 'idGuiaRemision'),
            'guiaRemision'      => data_get($params, 'guiaRemision'),

        ];
    }
}
