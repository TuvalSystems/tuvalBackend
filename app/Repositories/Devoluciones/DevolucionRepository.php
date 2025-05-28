<?php

namespace App\Repositories\Devoluciones;

use App\Models\GpPedido;
use App\Models\devoluciones\GpDevoluciones;
use App\Models\devoluciones\GpDevolucionesDetalle;
use App\Models\devoluciones\GpFacturaAnulada;
use App\Repositories\Devoluciones\Contracts\DevolucionRepositoryInterface;


class DevolucionRepository implements DevolucionRepositoryInterface
{

    public function isInvoiceAlreadyVoided($pedido , $devolucion)
    {
        return GpFacturaAnulada::where('factura', $pedido->factura)->where('devolucion_id', $devolucion['id'])
            ->where('pedido_id', $pedido->id)->where('estado', 'enviada')->first();
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


    public function getOrderbyId($pedido_id)
    {

        return GpPedido::find($pedido_id);
    }

    public function getDetailDevolucion($devolucion_id, $despachadoParcial = null)
    {
        $query = GpDevolucionesDetalle::where('devolucion_id', $devolucion_id);
        if (!is_null($despachadoParcial)) {
            $query->where('despachado_parcial', $despachadoParcial);
        }
       return $query->where('seleccionado','>',0)->get()
            ->filter(function ($item) {
                // ✅ Verificación de datos correctos y no nulos
                return is_array($item->toArray()) && isset($item['ART_CODIGO']);
            })
            ->map(function ($item) {
                // 🚀 Convertimos cada item a array para asegurar tipado
                return $item->toArray();
            })
            ->toArray();
    }


    public function validateItemsPartialDispatch($devolucion)
    {
        return GpDevolucionesDetalle::where('devolucion_id', $devolucion['id'])
            ->where('despachado_parcial', 1)
            ->count();
    }
}
