<?php

namespace App\Services\Aprobaciones\Contracts;

interface ApprovalReservationServiceInterface
{
    // Define los métodos que debe implementar el servicio
    public function checkValidateApprovalReservation(int $id);
}
