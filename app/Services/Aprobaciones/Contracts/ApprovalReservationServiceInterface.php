<?php

namespace App\Services\Aprobaciones\Contracts;

interface ApprovalReservationServiceInterface
{
    public function checkValidateApprovalReservation(int $id): ?string;
}
