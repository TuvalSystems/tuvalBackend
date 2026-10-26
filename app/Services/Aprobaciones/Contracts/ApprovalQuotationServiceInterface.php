<?php

namespace App\Services\Aprobaciones\Contracts;

use App\Models\Admin\Usuarios\GpUsuario;
use Illuminate\Database\Eloquent\Collection;

interface ApprovalQuotationServiceInterface
{
    public function approveQuotation(array $data): array;
    public function getAllQuotesPendingApprovals(int $ptoVta, GpUsuario $user);
    public function getAllQuotesForGlobalConsultation(string $ptoVta): Collection;
    /**  aprobacicon comercial de cotizacion */
    public function approveComercialQuote(int $id, GpUsuario $user);
    public function denyQuotationBeenWaiting(int $id, GpUsuario $user);

}
