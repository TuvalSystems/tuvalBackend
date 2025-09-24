<?php

namespace App\Notifications\Venta\Reservas;

use App\Models\GpUsuario;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReservationRequestNotification extends Notification implements ShouldQueue
{
    use Queueable;


    public function __construct(public string $message, public string $reservation, public GpUsuario $gpUsuario)
    {
        $this->afterCommit = true;          // garantiza enviar tras commit
        $this->onQueue('notifications');
    }


    public function via($notifiable)
    {
        return ['database', 'broadcast'];;
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
            'user_id' => $this->gpUsuario->id,
            'mensaje' => "Nueva solicitud de reserva de inventario realizada por {$this->gpUsuario->USERID} para la cotización {$this->reservation}",
            'estado' => 'nuevo',
        ];
    }

    public function toBroadcast($notifiable)
    {
        return new BroadcastMessage([
            'titulo'  => $this->message,
            'byUser'  => $this->gpUsuario->USERID,
            'message' => "Nueva solicitud de reserva de inventario realizada por {$this->gpUsuario->USERID} para la cotización {$this->reservation}",
        ]);
    }


    // public function toMail($notifiable)
    // {
    //     return (new MailMessage)
    //         ->line('The introduction to the notification.')
    //         ->action('Notification Action', url('/'))
    //         ->line('Thank you for using our application!');
    // }


    // public function toArray($notifiable)
    // {
    //     return [
    //         //
    //     ];
    // }
}
