<?php

namespace App\Services\Builders\Venta\Contracts;

use App\Models\GpPedido;
use App\Models\GpPedidoDetalle;
use App\Models\GpPedidoDetalleProveedores;
use App\Models\GpPedidoDetalleStock;
use App\Models\GpPuntoVenta;
use App\Models\GpUsuario;

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
