<?php

namespace App\Notifications\Venta\Reservas;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class ReservaPorExpirarNotification extends Notification implements ShouldQueue
{
    use Queueable;


    public function __construct(public string $title, public string $reservation)
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
            'titulo' => $this->title,
            'mensaje' => "Tu reserva para la cotizacion {$this->reservation} expira mañana y se liberará el stock.",

        ];
    }

    public function toBroadcast($notifiable)
    {
        return new BroadcastMessage([
            'titulo'  => $this->title,
            'message' => "Tu reserva para la cotizacion {$this->reservation} expira mañana y se liberará el stock.",
        ]);
    }
}
