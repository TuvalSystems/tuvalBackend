<?php

namespace App\Notifications\Venta\Reservas;

use App\Models\GpUsuario;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class WarehouseRequestNotificaction extends Notification implements ShouldQueue
{
    use Queueable;


    public function __construct(public string $message, public string $reservation, public GpUsuario $gpUsuario)
    {
        $this->afterCommit = true;
        $this->onQueue('notifications');
    }


    public function via($notifiable)
    {
        return ['database', 'broadcast'];
    }


    public function viaQueues()
    {
        return [
            'database'  => 'notifications',
            'broadcast' => 'broadcasts',
        ];
    }


    public function toDatabase($notifiable)
    {
        return [
            'titulo' => $this->message,
            // 'user_id' => $this->gpUsuario->id,
            'mensaje' => "Nueva solicitud de reserva de inventario realizada por {$this->gpUsuario->USERNAME} para la cotización {$this->reservation}",
        ];
    }

    public function toBroadcast($notifiable)
    {
        return new BroadcastMessage([
            'titulo'  => $this->message,
            'byUser'  => $this->gpUsuario->USERID,
            'message' => "Nueva solicitud de reserva de inventario realizada por {$this->gpUsuario->USERNAME} para la cotización {$this->reservation}",
        ]);
    }
}
