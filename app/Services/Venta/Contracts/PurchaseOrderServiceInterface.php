<?php

namespace App\Services\Venta\Contracts;

use App\Models\GpPedido;

interface PurchaseOrderServiceInterface
{
    // Define los métodos que debe implementar el servicio


    public function getViewPurchaseOrder();

    public function getOrdersDispatchPending();

    /** para el terma de check enviar facturas- funciones */

    public function checkPurchaseOrder(GpPedido $pedido);
    public function checkPurchaseOrdersV2(GpPedido $pedido): GpPedido;
    public function sendPurchaseOrders();

    public function sendPurchaseOrdersV2();
    public function checkPurchaseOrderRecib(GpPedido $pedido);
}
