<?php

namespace App\Services\Compras\Recepciones\Contracts;

use App\Models\Admin\Usuarios\GpUsuario;

interface PurchaseReceiptLItemServiceInterface
{
    // Define los métodos que debe implementar el servicio
    public function receivedL(array $data, GpUsuario $user);
    public function checkOrderReception($order, $detailOrder, $user);

     public function printPurchaseReceiptL($id);
}
