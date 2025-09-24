<?php

namespace App\Events\Venta\Reserva;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Support\Facades\Log;

class ReservationApprovalsChanged implements ShouldBroadcastNow
{

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
         Log::info('approvals-reservations', ['msg' => $this->action, 'reservationId' => $this->reservationId, 'byUser' => $this->byUser]);
        return [
            'action' => $this->action,
            'reservationId' => $this->reservationId,
            'byUser' => $this->byUser,
        ];
    }
}
