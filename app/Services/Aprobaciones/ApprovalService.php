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

     public function checkoutApprovalDevoluciones($data){

        $tipoDevolucion = Arr::get($data, 'devolucion.tipo_devolucion');
        $volverAFacturar = Arr::get($data, 'devolucion.volver_a_facturar', false);
        $despachado = Arr::get($data, 'pedido.despachado', null);
        $detallePedido = Arr::get($data, 'detallepedido', []);
        $fechaFactura = Arr::get($data, 'pedido.fecha_factura', null);
        $tieneL = collect($detallePedido)->contains(fn($detalle)=> strtolower(Arr::get($detalle, 'ART_TIPO_COMPRA', '')) === 'l' || Arr::get($detalle, 'ART_TIPO_COMPRA', '') === 'L');

        if ($tipoDevolucion === 'parcial') {
            if (!$volverAFacturar && !$despachado && !$tieneL) {
                return 'no';
            }
        }

        if ($tipoDevolucion === 'total' && !$volverAFacturar) {
            if ($despachado && !$tieneL) {
                return 'no';
            }

            if ($despachado && !$tieneL && $fechaFactura) {
                $origin = new DateTime($fechaFactura);
                $target = new DateTime('now');

                if ($origin->diff($target)->days<=7) {
                    return 'no';
                }
            }
        }

        return 'yes';

     }
}
