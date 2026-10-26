<?php

namespace App\Services\Builders\Bodega;

use App\Models\Inventario\Despachos\GpOrdenDespacho;
use App\Models\Ventas\Pedidos\GpPedido;
use App\Services\Builders\Bodega\Contracts\DispatchOrderParamsBuilderInterface;

class DispatchOrderParamsBuilder implements DispatchOrderParamsBuilderInterface
{
    public function build(GpPedido $pedido, string $estado, ?string $fechaLiberacion = null): GpOrdenDespacho
    {
        $despacho = new GpOrdenDespacho();
        $despacho->fill([
            'pedido_id'             => $pedido->id,
            'estado'                => $estado,
            'fecha_hora_liberacion' => $fechaLiberacion,
            'PTO_VTA_ID'            => $pedido->PTO_VTA_ID,
            'BOD_BODEGA'            => $pedido->BOD_BODEGA
        ]);

        return $despacho;
    }
}
