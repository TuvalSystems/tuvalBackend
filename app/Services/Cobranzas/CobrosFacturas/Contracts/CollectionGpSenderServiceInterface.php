<?php

namespace App\Services\Cobranzas\CobrosFacturas\Contracts;

interface CollectionGpSenderServiceInterface
{
    public function sendCollectionToGp(int $collectionId): array;
    public function checkValidateDispatchInvoice(int $collectionId): void;
}
