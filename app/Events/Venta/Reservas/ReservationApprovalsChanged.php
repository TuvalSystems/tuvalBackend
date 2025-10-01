<?php

namespace App\Events\Venta\Reservas;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class ReservationApprovalsChanged implements ShouldBroadcast
{

    public $broadcastQueue = 'broadcasts';
    public $connection = 'database';
    public $afterCommit = true;
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
