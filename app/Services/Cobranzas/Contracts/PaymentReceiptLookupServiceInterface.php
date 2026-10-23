<?php

namespace App\Services\Cobranzas\Contracts;

use Illuminate\Support\Collection;

interface PaymentReceiptLookupServiceInterface
{
    public function getPaymentsByDepositReference(string $reference): Collection;
}
