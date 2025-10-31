<?php

namespace App\Services\Bodegas\Contracts;

interface DeliveryNoteCommonServiceInterface
{
    // Define los métodos que debe implementar el servicio

     public function getNextGuideSequential(string $establecimiento, string $ptoEmision) : string;
}
