<?php

namespace App\Services\Builders\Bodega;

use App\Models\GpPedido;
use App\Models\GpPedidoDetalle;
use App\Models\traslados\GpTrasladosVentas;
use App\Services\Builders\Bodega\Contracts\SalesTransferParamsBuilderInterface;

class SalesTransferParamsBuilder implements SalesTransferParamsBuilderInterface
{


    public function build(GpPedido $pedido, GpPedidoDetalle $detalle) : GpTrasladosVentas
    {
        $tr = new GpTrasladosVentas();
        $tr->fill([
            'pedido_id'                     => $pedido->id,
            'detalle_id'                => $detalle->id,
            'detalle_stocks_id'         => $detalle->detalle_stocks_id,
            'ART_CODIGO'                => $detalle->ART_CODIGO,
            'ART_DESCRIPCION'           => $detalle->ART_DESCRIPCION_ORIGINAL,
            'ART_CODIGO_TRES_LETRAS'    => $detalle->ART_CODIGO_TRES_LETRAS,
            'ART_PLAN_UMEDIDA'          => $detalle->ART_PLAN_UMEDIDA,
            'ART_PESO'                  => $detalle->ART_PESO,
            'cantidad'                  => $detalle->seleccionado,
            'bodega_origen'             => $detalle->STO_ART_BODEGA,
            'bodega_destino'            => $pedido->BOD_BODEGA,
        ]);
        return $tr;
    }
}
