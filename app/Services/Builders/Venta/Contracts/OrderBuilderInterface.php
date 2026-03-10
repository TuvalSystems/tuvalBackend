<?php

namespace App\Services\Builders\Venta\Contracts;

use App\Models\GpPedido;
use App\Models\GpPuntoVenta;
use App\Models\GpUsuario;

interface OrderBuilderInterface
{
    // Define los métodos que debe implementar el servicio
    public function formPayloadOrderDemand(array $infoCreator, array $payload, array  $approvalComercial, GpPuntoVenta $puntoVenta, string  $requiereAprobacionCrediticia);
    public function formPayloadOrderDetail($orderId,  $item, $i);
    public function formPayloadOrderDetailProvider($provider, $orderId, $detailOrder, $recibido);
    public function formPayloadOrderDetailStock($item, $orderId, $detailOrderId, $disponible, $reservado, int $decimalQty);


    /** builder que arma un pedido desde crearcotizacion */
    public function formPayloadOrderV2(array $data, $user, GpPuntoVenta $ptoVta,  array $requiredApprovalCommercial, array $extras): GpPedido;


    public function formPayloadInvoiceExistence(array $data, GpUsuario $user, GpPuntoVenta $ptoVta): GpPedido;
}
