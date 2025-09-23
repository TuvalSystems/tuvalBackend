<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
// use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class WsPing implements ShouldBroadcast
{
    public function broadcastOn()
    {
        return new Channel('test');
    }
    public function broadcastAs()
    {
        return 'Ping';
    }
    public function broadcastWith()
    {
        return ['msg' => 'hola-queued'];
    }
}
