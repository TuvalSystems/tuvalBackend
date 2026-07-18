<?php

namespace App\Repositories\Venta\Contracts;

use App\Models\GpCotizacion;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

interface InventoryReservationRepositoryInterface
{
    public function getDetailsForTransferReservation(GpCotizacion $quote): EloquentCollection;

    public function checkExistingReservationTransfer(GpCotizacion $quote): bool;

    public function getAllTransferReservedByWarehouses(?string $warehouse): EloquentCollection;

    public function getAllTransferReservedByOriginAndDestination(string $waOrigin, string $waDestination, ?string $quotation = null): EloquentCollection;

    public function findCountPendingGuidesByOriginWarehouse(?string $bodOrigin): EloquentCollection;

    public function findGuidesPendingsByOriginWarehouse(string $bodOrigin, string $destId): EloquentCollection;
}
