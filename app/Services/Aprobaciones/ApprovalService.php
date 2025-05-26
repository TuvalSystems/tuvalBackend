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
        $tieneL = collect($detallePedido)->contains(fn($detalle) => strtolower(Arr::get($detalle, 'ART_TIPO_COMPRA', '')) === 'l' || Arr::get($detalle, 'ART_TIPO_COMPRA', '') === 'L');
        $requiresDispatch = collect($detallePedido)->contains(
            fn($detalle) => in_array(Arr::get($detalle, 'despacho', false), [true, '1', 1], true)
        );
        $daysDifference = $this->daysDifference($fechaFactura);

        if ($requiresDispatch) {
            return 'yes';
        }

        if ($tipoDevolucion === 'parcial') {
            if ($daysDifference <= 7) {
                if (!$volverAFacturar && !$tieneL) {
                    return 'no';
                }
            }
        }

        if ($tipoDevolucion === 'total' && !$volverAFacturar) {
            if ($tieneL) {
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
