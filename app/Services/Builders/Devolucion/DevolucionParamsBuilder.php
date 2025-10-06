<?php

namespace App\Services\Builders\Devolucion;

use App\Models\devoluciones\GpDevoluciones;
use App\Models\GpPedido;
use App\Services\Builders\Devolucion\Contracts\DevolucionParamsBuilderInterface;

class DevolucionParamsBuilder implements DevolucionParamsBuilderInterface
{

    public function __construct()
    {
        // Inyecta dependencias si es necesario
    }


    public function builderParamsDevolutionAdjustment(GpDevoluciones $devolucion, GpPedido $pedido, $userId, $items)
    {
        $header = [
            'idCompany' => config('services.api_gp.company'),
            'documento' =>  $devolucion->devolucion,
            'pedidoOrigen' => $pedido->pedido,
            'bodega' => $pedido->BOD_BODEGA,
            'observacion' => $devolucion->DOC_GENERADO,
            'usuario' => $userId ?? 'adminWeb',
        ];
        return [
            'cabecera' => $header,
            'detalle' => $items
        ];
    }
}
