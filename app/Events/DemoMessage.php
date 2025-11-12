<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
class DemoMessage implements ShouldBroadcast
{
    public function __construct(public string $msg = 'hola') {}

    public function broadcastOn() { return new Channel('test'); }     // canal público
    public function broadcastAs() { return 'DemoMessage'; }           // nombre del evento
    public function broadcastWith(): array
    {
        return [
            'msg'  => $this->msg,
            'from' => 'server',
            'time' => now()->toIso8601String(),
        ];
    }
}
