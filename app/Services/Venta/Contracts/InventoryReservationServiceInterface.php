<?php

namespace App\Services\Venta\Contracts;

use App\Models\GpUsuario;

interface InventoryReservationServiceInterface
{
    // Define los métodos que debe implementar el servicio

     public function hasAnyReserveState(int $quotationId);
     public function saveQuotationHeaderReserved(array $reserva,  GpUsuario $user);
}
