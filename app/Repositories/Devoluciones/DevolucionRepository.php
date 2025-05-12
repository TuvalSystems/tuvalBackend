<?php

namespace App\Repositories\Devoluciones;

use App\Models\GpPedido;
use App\Models\devoluciones\GpDevoluciones;
use App\Models\devoluciones\GpDevolucionesDetalle;
use App\Models\devoluciones\GpFacturaAnulada;
use App\Repositories\Devoluciones\Contracts\DevolucionRepositoryInterface;


class DevolucionRepository implements DevolucionRepositoryInterface
{

    public function isInvoiceAlreadyVoided($pedido)
    {
        return GpFacturaAnulada::where('factura', $pedido->factura)
            ->where('pedido_id', $pedido->id)->where('estado', 'facturado')->first();
    }

    public function validateInvoiceAnulation($pedido)
    {
        return GpPedido::where(function ($q) {
            $q->where('despachado', 0)->orWhereNull('despachado');
        })->where(function ($q) {
            $q->where('despacho_en_proceso', 0)->orWhereNull('despacho_en_proceso');
        })
            ->where('id', $pedido['id'])->first();
    }

    public function countsReturnsByOrders($pedido, $devolucion)
    {

        return GpDevoluciones::where('pedido_id', $pedido['id'])->where('id', '!=', $devolucion['id'])->where('estado', 'rechazada')->count();
    }

    public function validateItemLDetailInvoice($devolucion)
    {
        return GpDevolucionesDetalle::where('devolucion_id', $devolucion['id'])->where('ART_TIPO_COMPRA', 'L')->count();
    }
}
