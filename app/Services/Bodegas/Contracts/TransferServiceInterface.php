<?php

namespace App\Services\Bodegas\Contracts;

use App\Models\GpUsuario;

interface TransferServiceInterface
{
    // Define los métodos que debe implementar el servicio

    public function fetchSalesTransfers($id, $pedido = null, $compartimiento = null);
    public function checkOrderTranfer();
    public function cancelPicking(array $payload, GpUsuario $user);
}
