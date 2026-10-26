<?php

namespace App\Jobs\Venta\Reservas;

use App\Events\Venta\Reservas\ReservedListAuthorizedChanged;
use App\Models\Ventas\Cotizaciones\GpCotizacion;
use App\Models\Admin\Usuarios\GpUsuario;
use App\Notifications\User\UserNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendDennyReservationForStockNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(public int $quoteId, public int $authId)
    {
        $this->onQueue('notifications');
        $this->afterCommit = true;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $cotizacion = GpCotizacion::with(['reserva' =>  function ($q) {
            $q->select('id', 'cotizacion_id', 'fe_solicitud_reserva', 'tipo_reserva_id', 'tipo_traslado_reserva_id', 'user_ingreso_reserva_id');
        }])->select('id', 'cotizacion')->whereKey($this->quoteId)
            ->first();
        if (!$cotizacion) {
            return;
        }

        $userSolicitud = GpUsuario::find($cotizacion->reserva->user_ingreso_reserva_id);
        $authUser = GpUsuario::find($this->authId);

        event(new ReservedListAuthorizedChanged('refresh', $cotizacion->id, $userSolicitud->USERID));
        $title  = 'Reserva Rechazada';
        $msg    = "Solicitud de reserva rechazada por {$authUser->USERNAME} para la cotización {$cotizacion->cotizacion} por falta de stock disponible";
        $userSolicitud->notify(new UserNotification($title, $msg));
    }
}
