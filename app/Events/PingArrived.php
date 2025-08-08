<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PingArrived implements ShouldBroadcast
{
     public function __construct(public string $message) {}

    public function broadcastOn(): array
    {
        return [new Channel('public.pings')]; // canal público
    }

    public function broadcastAs(): string
    {
        return 'test-event'; // nombre del evento en el front
    }
}
