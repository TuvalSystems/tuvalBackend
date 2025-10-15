<?php

namespace App\Jobs\Venta\Reservas;

use App\Events\Venta\Reservas\ReservedListAuthorizedChanged;
use App\Models\GpUsuario;
use App\Models\GpCotizacion;
use App\Notifications\User\UserNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class SendApprovalReservationNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $cotizacionId, public int $autorizadoId)
    {
        $this->onQueue('notifications');   // cola por defecto de este Job
        $this->afterCommit = true;
    }


    public function handle()
    {
        $cotizacion = GpCotizacion::with(['reserva' =>  function ($q) {
            $q->select('id', 'cotizacion_id', 'fe_solicitud_reserva', 'tipo_reserva_id', 'tipo_traslado_reserva_id', 'user_ingreso_reserva_id');
        }])->select('id', 'cotizacion')->whereKey($this->cotizacionId)
            ->first();
        if (!$cotizacion) {
            return;
        }
        $user = GpUsuario::find($cotizacion->reserva->user_ingreso_reserva_id);
        $userAutorizado = GpUsuario::find($this->autorizadoId);

        event(new ReservedListAuthorizedChanged('refresh', $cotizacion->id, $user->USERID));

        $title = 'Reserva Aprobada';
        $mensaje = "Solicitude de reserva aprovada por {$userAutorizado->USERID} para la cotización {$cotizacion->cotizacion}";
        $user->notify(new UserNotification($title,  $mensaje));
    }
}
