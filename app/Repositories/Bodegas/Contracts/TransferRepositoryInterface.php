<?php

namespace App\Repositories\Bodegas\Contracts;

interface TransferRepositoryInterface
{
    public function findPendingSalesTransfersByOriginWarehouse(string $bodega, ?string $orderNumber = null);

    public function getAllSalesTransfersByWarehouse(string $warehouse);
    public function findCountPendingGuidesByOriginWarehouse(string $bodOrigin);
    public function findGuidesToWarehouseDestinoAndOrigin($bodOrigin, $destId);
    public function getAllSalesTransfersByWarehouseOriginAndDestination(string $origin, string $destination, ?string $orderNumber = null);
}
