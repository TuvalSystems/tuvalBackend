<?php

namespace App\Http\Controllers\test;

use App\Http\Controllers\Controller;
use App\Models\GpPedido;
use App\Services\Venta\Contracts\PurchaseOrderServiceInterface;

class TestController extends Controller
{
    public function __construct(protected readonly PurchaseOrderServiceInterface $purchase) {}


    public function crearOrdenCompraItemsL(int $id)
    {
        $pedido = GpPedido::find($id);
        return $this->purchase->checkPurchaseOrder($pedido);
    }
}
