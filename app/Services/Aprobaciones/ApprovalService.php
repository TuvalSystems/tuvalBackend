<?php

namespace App\Services\Aprobaciones;

use App\Services\Aprobaciones\Contracts\ApprovalServiceInterface;
use DateTime;
use Illuminate\Support\Arr;

class ApprovalService implements ApprovalServiceInterface
{


    // aprobacion comercial del pedido en caso de que tenga

    public function checkoutApprovalComercial($data, $order = false)
    {
        // TODO: Implement checkoutApprovalComercial() method.



    }

    public function checkoutApprovalDevoluciones($data)
    {

        $tipoDevolucion = Arr::get($data, 'devolucion.tipo_devolucion');
        $volverAFacturar = Arr::get($data, 'devolucion.volver_a_facturar', false);
        // $despachado = Arr::get($data, 'pedido.despachado', null);
        $detallePedido = Arr::get($data, 'detallepedido', []);
        $fechaFactura = Arr::get($data, 'pedido.fecha_factura', null);
        $daysDifference = $this->daysDifference($fechaFactura);
        $hasTypeLItems = collect($detallePedido)->contains(
            fn($detalle) => strtolower(Arr::get($detalle, 'ART_TIPO_COMPRA', '')) === 'l'
        );

        $requiresDispatch = collect($detallePedido)->contains(
            fn($detalle) => in_array(Arr::get($detalle, 'despacho', false), [true, '1', 1], true)
        );


        if ($requiresDispatch) {
            return 'yes';
        }

        if ($tipoDevolucion === 'parcial') {
            if ($daysDifference <= 7) {
                if (!$volverAFacturar && !$hasTypeLItems) {
                    return 'no';
                }
            }
        }

        if ($tipoDevolucion === 'total' && !$volverAFacturar) {
            if ($hasTypeLItems) {
                return $daysDifference <= 7 ? 'no' : 'yes';
            }
            return $daysDifference <= 7 ? 'no' : 'yes';
        }

        return 'yes';
    }


    public function daysDifference($fecha)
    {
        if (!$fecha)
            return 0;

        $origin = new DateTime($fecha);
        $target = new DateTime('now');
        return $origin->diff($target)->days;
    }
}
