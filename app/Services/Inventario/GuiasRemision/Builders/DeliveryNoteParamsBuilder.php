<?php

namespace App\Services\Inventario\GuiasRemision\Builders;

use App\Services\Inventario\GuiasRemision\Builders\Contracts\DeliveryNoteParamsBuilderInterface;

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
