<?php

namespace App\Services\Cobranzas;

use App\Repositories\Cobranzas\Contracts\CollectionRepositoryInterface;
use App\Services\Cobranzas\Contracts\PaymentReceiptLookupServiceInterface;
use Illuminate\Support\Collection;

class PaymentReceiptLookupService implements PaymentReceiptLookupServiceInterface
{
    public function __construct(
        protected readonly CollectionRepositoryInterface $collectionRepository
    ) {}

    public function getPaymentsByDepositReference(string $reference): Collection
    {
        return $this->collectionRepository->getPaymentsByDepositReference($reference);
    }
}
