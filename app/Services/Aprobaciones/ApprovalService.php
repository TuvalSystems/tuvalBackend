<?php

namespace App\Services\Aprobaciones;

use DateTime;
use App\Models\GpPedido;
use Illuminate\Support\Arr;
use App\Models\GpPedidoDetalle;
use App\Models\despachos\GpGuiaRemisionDetalle;
use App\Services\Aprobaciones\Contracts\ApprovalServiceInterface;

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
        $detallePedido = Arr::get($data, 'detallepedido', []);
        $fechaFactura = Arr::get($data, 'pedido.fecha_factura', null);
        $daysDifference = $this->daysDifference($fechaFactura);
        $hasTypeLItems = collect($detallePedido)->contains(
            fn($detalle) => strtolower(Arr::get($detalle, 'ART_TIPO_COMPRA', '')) === 'l'
        );

        // Verifica si el pedido tiene detalles de orden despachados
        // Esto es necesario para determinar si se requiere despacho
        // en caso de que la devolucion sea parcial o total
        // y no se vuelva a facturar
        $requiresDispatch = $this->hasDispatchedOrderDetails(Arr::get($data, 'pedido', []), $detallePedido, $volverAFacturar);


        if ($requiresDispatch) {
            return 'yes';
        }

        if ($tipoDevolucion === 'parcial') {
            if ($volverAFacturar) {
                return 'yes';
            }
            if ($hasTypeLItems) {
                return 'yes';
            }
            return $daysDifference <= 7 ? 'no' : 'yes';
        }

        if ($tipoDevolucion === 'total') {
            if ($volverAFacturar) {
                return 'yes';
            }
            if ($hasTypeLItems) {
                return 'yes';
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


    public function hasDispatchedOrderDetails($pedido, $detallepedido, $volverAFacturar): bool
    {
        if ($volverAFacturar === true) {
            $lastOrder = GpPedido::where('cotizacion_id', $pedido['cotizacion_id'])
                ->where('estado', '!=', 'anulada')
                ->orderByDesc('id')
                ->first();
            return GpPedidoDetalle::whereIn('pedido_id', $lastOrder->id)
                ->where('trasladado', '>', 0)
                ->exists();
        }

        $pedidoConsulta = GpPedido::find($pedido['id']);
        if (!empty($pedidoConsulta['factura_origen'])) {
            $pedido_origen = GpPedido::where('factura', operator: $pedido['factura_origen'])->orderByDesc('id')->first();
            return $pedido_origen['despachado'] ?? $pedido_origen->despachado;
        }

        $countDetalle = collect(value: $detallepedido)->filter(function ($item) {
            return isset($item['despachado']) && (float) $item['despachado'] > 0 && $item['despacho'] === true;
        })->count();
        return $countDetalle > 0;
    }
}
