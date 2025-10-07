<?php

namespace App\Jobs\Venta\Reservas;

use App\Models\GpUsuario;
use App\Notifications\Venta\Reservas\UserReservedNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class NotifyReservationRequesterOrderCreatedJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;


    public function __construct(public string $orderNumber, public int $userSolicitanteId)
    {

        $this->onQueue('notifications');
        $this->afterCommit = true;
    }


    public function handle()
    {
        $user = GpUsuario::find($this->userSolicitanteId);
        $title = 'Pedido Creado';
        $mensaje = "Pedido {$this->orderNumber} creado a partir de una solicitud de reserva";
        $user->notify(
            (new UserReservedNotification($title, $mensaje))
                ->onQueue('notifications')
        );
    }
}
