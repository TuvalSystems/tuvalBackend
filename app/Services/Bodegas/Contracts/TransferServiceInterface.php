<?php

namespace App\Services\Bodegas\Contracts;

interface TransferServiceInterface
{
    // Define los métodos que debe implementar el servicio

    public function fetchSalesTransfers($id, $pedido = null, $compartimiento = null);
    public function checkOrderTranfer();
}
