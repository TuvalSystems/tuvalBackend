<?php

namespace App\Services\Ventas\Aprobaciones\Contracts;

interface ApprovalReservationServiceInterface
{
    public function checkValidateApprovalReservation(int $id): ?string;
}
