<?php

namespace App\Services\Cobranzas\CobrosFacturas\Contracts;

use App\Models\GpUsuario;
use Illuminate\Support\Collection;

interface InvoiceCollectionServiceInterface
{
    // Define los métodos

    public function getStatusBoxedUser(int $pvId, string $pvCode, ?string $date, GpUsuario $user): array;
    public function openCashBoxed(int $pvId,  GpUsuario $user);
    public function generateClientAccountStatementPdf(int $clientId): array;
    public function generateClientAccountStatementExcel(int $clientId): array;

    public function getInvoiceCollectionsByClient(int $id) : array ;
    public function getUnappliedCollectionsByClient(int $id): ?Collection;
    public function getBanks(): array;
     public function retentionReceiptExistsForClient(int $clientId, string $receiptNumber): bool;
}
