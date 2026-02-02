<?php

namespace App\Events\Venta\Reservas;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReservedListAuthorizedChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;


    public $broadcastQueue = 'broadcasts';
    public $afterCommit = true;
    public function __construct(public string $action, public int $reservationId, public ?string $byUser = null) {}
    public function broadcastOn()
    {
        return new PrivateChannel('refresh-authorized-reservations');
    }

    public function broadcastAs()
    {
        return 'ReservedListAuthorizedChanged';
    }
    public function broadcastWith()
    {
        return [
            'action'         => $this->action,
            'reservationId'  => $this->reservationId,
            'byUser'         => $this->byUser,
            'at'             => now()->toIso8601String(),
        ];
    }
}
