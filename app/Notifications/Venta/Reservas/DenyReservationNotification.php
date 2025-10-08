<?php

namespace App\Notifications\Venta\Reservas;

use App\Models\GpUsuario;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class DenyReservationNotification extends Notification  implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct(public string $message, public string $reservation, public GpUsuario $gpUsuario)
    {
        $this->afterCommit = true;          // garantiza enviar tras commit
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
            'title' => $this->message,
            'message' => "Solicitude de reserva denegada por {$this->gpUsuario->USERNAME} para la cotización {$this->reservation}",

        ];
    }

    public function toBroadcast($notifiable)
    {
        return new BroadcastMessage([
            'title'  => $this->message,
            'message' => "Solicitude de reserva denegada por {$this->gpUsuario->USERNAME} para la cotización {$this->reservation}",
        ]);
    }


}
