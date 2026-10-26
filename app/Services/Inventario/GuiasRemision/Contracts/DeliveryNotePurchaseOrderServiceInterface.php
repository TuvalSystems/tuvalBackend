<?php

namespace App\Services\Inventario\GuiasRemision\Contracts;

use Throwable;

interface DeliveryNotePurchaseOrderServiceInterface
{
    public function enqueuePendingGuides(int $limit = 200): int;

    public function generatePurchaseOrder(int $purchaseOrderId): void;

    public function recordInterruptedAttempt(int $purchaseOrderId, Throwable $exception): void;
}
