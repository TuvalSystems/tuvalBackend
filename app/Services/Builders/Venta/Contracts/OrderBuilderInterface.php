<?php

namespace App\Services\Builders\Venta\Contracts;

use App\Models\Ventas\Pedidos\GpPedido;
use App\Models\Ventas\Pedidos\GpPedidoDetalle;
use App\Models\Ventas\Pedidos\GpPedidoDetalleProveedores;
use App\Models\Ventas\Pedidos\GpPedidoDetalleStock;
use App\Models\Admin\PuntosVenta\GpPuntoVenta;
use App\Models\Admin\Usuarios\GpUsuario;

interface OrderBuilderInterface
{
    // Define los métodos que debe implementar el servicio
    public function formPayloadOrderDemand(array $infoCreator, array $payload, array  $approvalComercial, GpPuntoVenta $puntoVenta, string  $requiereAprobacionCrediticia): GpPedido;
    public function formPayloadOrderDetail(int $orderId, array $item, int $i): GpPedidoDetalle;
    public function formPayloadOrderDetailProvider(array $provider, int $orderId, int $detailOrder, string $recibido): GpPedidoDetalleProveedores;
    public function formPayloadOrderDetailStock(mixed $item, int $orderId, int $detailOrderId, float $disponible, float $reservado): GpPedidoDetalleStock;


    /** builder que arma un pedido desde crearcotizacion */
    public function formPayloadOrderV2(array $data, GpUsuario $user, GpPuntoVenta $ptoVta,  array $requiredApprovalCommercial, array $extras): GpPedido;


    public function formPayloadInvoiceExistence(array $data, GpUsuario $user, GpPuntoVenta $ptoVta): GpPedido;
}
