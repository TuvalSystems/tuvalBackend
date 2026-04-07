<?php

namespace App\Repositories\Bodegas\Contracts;

interface TransferRepositoryInterface
{
    public function findPendingSalesTransfersByOriginWarehouse(string $bodega, ?string $orderNumber = null);
    public function findGuidesToWarehouseDestinoAndOrigin($bodOrigin, $destId);
}
