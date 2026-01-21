<?php

namespace App\Jobs\Venta\Reservas;

use App\Events\Venta\Reservas\ReservedListAuthorizedChanged;
use App\Models\GpUsuario;
use App\Models\reservas\GpReserva;
use App\Notifications\User\UserNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendDenyReservationNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;


    public function __construct(public int $reserveId, public int $autorizadoId)
    {
        $this->onQueue('notifications');   // cola por defecto de este Job
        $this->afterCommit = true;
    }


    public function handle()
    {
        $reserva = GpReserva::with('cotizacion')->find($this->reserveId);
        if (!$reserva) {
            return;
        }
        $quote = $reserva?->cotizacion;
        $userSolicitante = GpUsuario::find($reserva->user_ingreso_reserva_id);
        $userAutorizado = GpUsuario::find($this->autorizadoId);

        event(new ReservedListAuthorizedChanged('refresh', $quote->id, $userSolicitante->USERID));
        $title = 'Reserva Rechazada';
        $msg = "Solicitud de reserva denegada por {$userAutorizado->USERNAME} para la cotización {$quote->cotizacion}";
        $notification = (new UserNotification($title, $msg));
        $userSolicitante->notify($notification);
    }
}
