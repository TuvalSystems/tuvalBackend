<?php

namespace App\Events\Bodegas\Dashboard;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DispatchPendings implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $broadcastQueue = 'broadcasts';
    public function __construct(public string $action) {}

    public function broadcastOn()
    {
        return new Channel('refresh-dashboard-dispatch-pendings');
    }


    public function broadcastAs()
    {
        return 'dispatch.updated';
    }


    public function broadcastWith()
    {
        return [
            'action'         => $this->action,
            'at'             => now()->toIso8601String(),
        ];
    }
}
