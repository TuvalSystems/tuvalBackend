<?php

namespace App\Events\Venta\Reserva;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class ReservationApprovalsChanged implements ShouldBroadcast
{

    public $afterCommit = true; // se encola solo tras commit
    public $broadcastQueue = 'broadcasts';
    public function __construct(public string $action, public int $reservationId, public ?string $byUser = null) {}


    public function broadcastOn()
    {
        return new PrivateChannel('approvals-reservations');
    }

    public function broadcastAs()
    {
        return 'ReservationApprovalsChanged';
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
