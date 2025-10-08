<?php

namespace App\Notifications\Venta\Reservas;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class ReservedReleaseNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
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
            'title' => $this->title,
            'message' => "El stock de la reserva de la cotización {$this->reservation} ha sido liberado.",

        ];
    }

    public function toBroadcast($notifiable)
    {
        return new BroadcastMessage([
            'title'  => $this->title,
            'message' => "El stock de la reserva de la cotización {$this->reservation} ha sido liberado.",
        ]);
    }
}
