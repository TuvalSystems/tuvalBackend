<?php

namespace App\Services\Bodegas\Contracts;

use Illuminate\Support\Collection;

interface DeliveryNoteCommonServiceInterface
{
    // Define los métodos que debe implementar el servicio

    public function getNextGuideSequential(string $establecimiento, string $ptoEmision): string;
    public function groupItemsByOrderReservation(Collection $details): array;

    public function moveReservedToTransferredForTrfvLine($trl, $qty);

}
