<?php

namespace App\Services\Venta\Contracts;

interface PurchaseOrderServiceInterface
{
    // Define los métodos que debe implementar el servicio


    public function  getViewPurchaseOrder();

    public function getOrdersDispatchPending();
}
