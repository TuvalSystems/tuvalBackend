<?php

namespace App\Services\Builders\Venta\Contracts;

use App\Models\GpPedido;
use App\Models\GpPuntoVenta;
use App\Models\GpUsuario;

interface OrderBuilderInterface
{
    // Define los métodos que debe implementar el servicio

    public function fromPayloadOrder($cotizacionId, array $data,  array $approvalComercial, GpUsuario $user, $pv, $requiereAprobacionCrediticia, $motivoCrediticio, $fechaEntrega, $origen, $fFacturacionReserva = null, $bodega);

    public function formPayloadOrderDetail($orderId,  $item, $i);
    public function formPayloadOrderDetailProvider($provider, $orderId, $detailOrder, $recibido);
    public function formPayloadOrderDetailStock($item, $orderId, $detailOrderId, $disponible, $reservado);


    /** builder que arma un pedido desde crearcotizacion */
    public function formPayloadOrderV2(array $data, $user, GpPuntoVenta $ptoVta,  array $requiredApprovalCommercial, array $extras): GpPedido;
}
