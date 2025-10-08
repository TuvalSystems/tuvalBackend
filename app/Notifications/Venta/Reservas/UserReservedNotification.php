<?php

namespace App\Notifications\Venta\Reservas;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class UserReservedNotification extends Notification implements ShouldQueue
{
    use Queueable;


    public function __construct(public string $title, public string $message)
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
            'titulo' => $this->title,
            'mensaje' => $this->message,

        ];
    }

    public function toBroadcast($notifiable)
    {
        return new BroadcastMessage([
            'titulo' => $this->title,
            'message' => $this->message,
        ]);
    }
}
