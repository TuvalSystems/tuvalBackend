<?php

namespace App\Services\Devoluciones\Aprobaciones;

use App\Enums\Ventas\OrderStatus;
use App\Models\Ventas\Pedidos\GpPedido;
use App\Models\Ventas\Pedidos\GpPedidoDetalle;
use App\Services\Devoluciones\Aprobaciones\Contracts\ApprovalDevolutionServiceInterface;
use DateTime;
use Illuminate\Support\Arr;

class ApprovalDevolutionService implements ApprovalDevolutionServiceInterface
{

    public function __construct()
    {
        // Inyecta dependencias si es necesario
    }


    public function checkoutApprovalDevoluciones(array $data)
    {

        $tipoDevolucion     = Arr::get($data, 'devolucion.tipo_devolucion');
        $volverAFacturar    = Arr::get($data, 'devolucion.volver_a_facturar', false);
        $detallePedido      = Arr::get($data, 'detalle', []);
        $fechaFactura       = Arr::get($data, 'pedido.fecha_factura', null);
        $daysDifference     = $this->daysDifference($fechaFactura);
        $hasTypeLItems = collect($detallePedido)->contains(
            fn($detalle) => strtoupper(data_get($detalle, 'ART_TIPO_COMPRA', '')) === 'L'
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


    public function daysDifference(string $fecha)
    {
        if (!$fecha)
            return 0;

        $origin = new DateTime($fecha);
        $target = new DateTime('now');
        return $origin->diff($target)->days;
    }


    public function hasDispatchedOrderDetails(array $pedido, array  $detallepedido, bool $volverAFacturar): bool
    {
        if (collect($detallepedido)->contains(fn ($item) => data_get($item, '_despacho_verificado', false))) {
            return collect($detallepedido)->contains(fn ($item) => !data_get($item, 'es_servicio_logistico', false)
                && data_get($item, 'despacho', false) && (float) data_get($item, 'despachado', 0) > 0);
        }
        if ($volverAFacturar === true) {
            $lastOrder = GpPedido::where('cotizacion_id', $pedido['cotizacion_id'])
                ->whereNotIn('estado', [OrderStatus::ANULADA, OrderStatus::ELIMINADO, OrderStatus::RECHAZADO])
                ->orderByDesc('id')
                ->first();
            if (!$lastOrder) {
                return false;
            }
            $codigos = collect($detallepedido)->pluck('ART_CODIGO')->unique();
            return GpPedidoDetalle::where('pedido_id', $lastOrder->id)
                ->whereIn('ART_CODIGO', $codigos)
                ->where('trasladado', '>', 0)
                ->exists();
        }

        $pedidoConsulta     = GpPedido::find($pedido['id']);
        $tieneFacturaOrigen = !empty($pedidoConsulta->factura_origen);
        if ($tieneFacturaOrigen) {

            //validador para casos donde el pedido tiene despachado null se verifica en caso de que tenga factura origen y si haya items despachados parciales
            if ($pedidoConsulta->despachado === false || $pedidoConsulta->despachado === null) {
                $pedido_origen = GpPedido::where('factura', $pedido['factura_origen'])->orderByDesc('id')->first();
                if (is_null(optional($pedido_origen)->despachado) || $pedido_origen->despachado === false) {
                    return $this->hasDetailDispatched($detallepedido);
                }
            }

            //esta condicional valida que pedido tenga factura origen y despachado
            if ($pedidoConsulta->despachado === true || $pedidoConsulta->despachado === 1) {
                $codigos = collect($detallepedido)->pluck('ART_CODIGO')->unique();
                return GpPedidoDetalle::where('pedido_id', $pedidoConsulta->id)
                    ->whereIn('ART_CODIGO', $codigos)
                    ->where('trasladado', '>', 0)
                    ->exists();
            }
        }

        return $this->hasDetailDispatched($detallepedido);
    }

    public function hasDetailDispatched(array $detallepedido): bool
    {
        return collect($detallepedido)
            ->contains(fn($item) =>
            (float) data_get($item, 'despachado', 0) > 0
                && data_get($item, 'despacho', false) === true);
    }

}
