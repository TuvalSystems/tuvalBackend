<?php

declare(strict_types=1);

namespace App\Services\Cobranzas\Cobros;

use App\Repositories\Cobranzas\Contracts\CollectionRepositoryInterface;
use App\Services\Cobranzas\Cobros\Contracts\PaymentReceiptLookupServiceInterface;
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
