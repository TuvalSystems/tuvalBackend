<?php
namespace App\Repositories\Bodegas\Contracts;

interface PurchaseReceipRepositoryInterface
{

    public function getGeneratedOrderDetails(int $purchaseOrderId);

     public function getPurchaseReceiptLById(int $id);
}
