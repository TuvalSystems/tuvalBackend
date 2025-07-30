<?php

namespace App\Services\Bodegas\Contracts;

interface PurchaseReceiptLItemServiceInterface
{
    // Define los métodos que debe implementar el servicio

    public function checkOrderReception($order, $detailOrder, $user);
}
