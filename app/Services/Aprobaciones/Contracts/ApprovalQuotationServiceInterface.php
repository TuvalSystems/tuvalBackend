<?php

namespace App\Services\Aprobaciones\Contracts;

use App\Models\GpUsuario;
use Illuminate\Database\Eloquent\Collection;

interface ApprovalQuotationServiceInterface
{
    public function approveQuotation(array $data): array;
    public function getAllQuotesPendingApprovals($ptoVta, GpUsuario $user);
     public function getAllQuotesForGlobalConsultation(string $ptoVta): Collection;
}
