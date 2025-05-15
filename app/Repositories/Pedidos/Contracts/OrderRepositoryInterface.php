<?php

namespace App\Repositories\Pedidos\Contracts;

interface OrderRepositoryInterface
{
    public function getAllDetailsOrderById($id);

    public function getSuppliersByOrderAndItem($pedidoId, $itemId);
    public function getPurchaseOrdersBySupplier($proveedorId);

    public function getStockByItemAndWarehouse( int $itemId, string $warehouse);

    public function getPurchaseOrdersByOrderNumber(string $orderNumber);
}
