<?php

namespace App\Jobs\Inventario\Bodegas;

use App\Services\Inventario\GuiasRemision\Contracts\DeliveryNotePurchaseOrderServiceInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class GenerateDeliveryNotePurchaseOrderJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 120;
    public bool $failOnTimeout = true;
    public int $uniqueFor = 900;

    public function __construct(public int $purchaseOrderId)
    {
        $this->afterCommit = true;
        $this->onConnection('redis');
        $this->onQueue('sync-high');
    }

    public function uniqueId(): string
    {
        return 'delivery-note-purchase-order:' . $this->purchaseOrderId;
    }

    public function handle(DeliveryNotePurchaseOrderServiceInterface $service): void
    {
        $service->generatePurchaseOrder($this->purchaseOrderId);
    }

    public function failed(Throwable $exception): void
    {
        app(DeliveryNotePurchaseOrderServiceInterface::class)->recordInterruptedAttempt($this->purchaseOrderId, $exception);
    }
}
