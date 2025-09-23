<?php

namespace App\Services\Bodegas\Contracts;

interface PurchaseReceiptLItemServiceInterface
{
    // Define los métodos que debe implementar el servicio
    public function receivedL(array $data, $user);
    public function checkOrderReception($order, $detailOrder, $user);

     public function printPurchaseReceiptL($id);
}
