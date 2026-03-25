<?php

namespace App\Services\Aprobaciones\Contracts;

use App\Models\GpUsuario;

interface ApprovalQuotationServiceInterface
{
    public function approveQuotation(array $data): array;
    public function getAllQuotesPendingApprovals($ptoVta, GpUsuario $user);
}
