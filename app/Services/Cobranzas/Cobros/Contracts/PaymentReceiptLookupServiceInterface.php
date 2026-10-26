<?php

declare(strict_types=1);

namespace App\Services\Cobranzas\Cobros\Contracts;

use Illuminate\Support\Collection;

interface PaymentReceiptLookupServiceInterface
{
    public function getPaymentsByDepositReference(string $reference): Collection;
}
