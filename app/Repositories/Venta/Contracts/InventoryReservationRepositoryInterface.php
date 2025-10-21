<?php

namespace App\Repositories\Venta\Contracts;

use App\Models\GpCotizacion;

interface InventoryReservationRepositoryInterface
{
    // Define los métodos que debe implementar el repositorio

     public function getDetailsForTransferReservation(GpCotizacion $quote);
      public function checkExistingReservationTransfer(GpCotizacion $quote);
      public function getAllTransferReservedByWarehouses($warehouse);
      public function getAllTransferReservedByOriginAndDestination($waOrigin, $waDestination);
}
