<?php

namespace App\Services\Builders\Venta\Contracts;

use App\Models\GpUsuario;

interface OrderBuilderInterface
{
    // Define los métodos que debe implementar el servicio

    public function fromPayloadOrder($cotizacionId, array $data,  array $approvalComercial, GpUsuario $user, $pv, $requiereAprobacionCrediticia, $motivoCrediticio, $fechaEntrega, $origen, $fFacturacionReserva = null);

    public function formPayloadOrderDetail($orderId,  $item, $i);
    public function formPayloadOrderDetailProvider($provider, $orderId, $detailOrder, $recibido);
    public function formPayloadOrderDetailStock($item, $orderId, $detailOrderId, $disponible, $reservado);
}
