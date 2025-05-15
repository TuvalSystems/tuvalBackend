<?php
namespace App\Repositories\Pedidos;

use App\Models\GpPedidoDetalle;
use App\Repositories\Pedidos\Contracts\OrderRepositoryInterface;

class OrderRepository implements OrderRepositoryInterface
{

    public function getAllDetailsOrderById($id)
    {
        return GpPedidoDetalle::where('pedido_id', $id)->get();
    }
}
