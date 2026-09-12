<?php

namespace App\Events\Cobranzas\CierreCaja;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CollectionInvoiceUpdatedEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $broadcastQueue = 'broadcasts';

    public $afterCommit = true;

    public function __construct(
        public string $action,
        public int $boxId,
        public ?string $byUser = null
    ) {}

    public function broadcastOn()
    {
        return new PrivateChannel('collection-invoices');
    }

    public function broadcastAs()
    {
        return 'CollectionInvoiceChanged';
    }

    public function broadcastWith()
    {
        return [
            'action'  => $this->action,
            'boxId'   => $this->boxId,
            'byUser'  => $this->byUser,
            'at'      => now()->toIso8601String(),
        ];
    }
}
