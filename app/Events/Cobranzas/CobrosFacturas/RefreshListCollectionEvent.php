<?php

namespace App\Events\Cobranzas\CobrosFacturas;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RefreshListCollectionEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;
    public $broadcastQueue = 'broadcasts';
    public $afterCommit = true;

    public function __construct(
        public string $action,
        public int $collectionId,
        public ?string $byUser = null
    ) {
        //
    }


    public function broadcastOn()
    {
        return new PrivateChannel('refresh-list-collection');
    }

    public function broadcastAs()
    {
        return 'RefreshListCollection';
    }

    public function broadcastWith()
    {
        return [
            'action'         => $this->action,
            'collectionId'   => $this->collectionId,
            'byUser'         => $this->byUser,
            'at'             => now()->toIso8601String(),
        ];
    }
}
