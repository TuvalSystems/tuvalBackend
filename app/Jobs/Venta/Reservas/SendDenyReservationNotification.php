<?php

namespace App\Jobs\Venta\Reservas;

use App\Events\Venta\Reservas\ReservedListAuthorizedChanged;
use App\Models\GpCotizacion;
use App\Models\GpUsuario;
use App\Notifications\Venta\Reservas\DenyReservationNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendDenyReservationNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;


    public function __construct(public int $cotizacionId, public int $autorizadoId)
    {
        $this->onQueue('notifications');   // cola por defecto de este Job
        $this->afterCommit = true;
    }


    public function handle()
    {
        $cotizacion = GpCotizacion::select('id', 'cotizacion', 'co_usrIngSolResInv')->whereKey($this->cotizacionId)
            ->first();
        if (!$cotizacion) {
            return;
        }
        $user = GpUsuario::find($cotizacion->co_usrIngSolResInv);
        $userAutorizado = GpUsuario::find($this->autorizadoId);
        $user->notify(new DenyReservationNotification('Reserva Rechazada', $cotizacion->cotizacion, $userAutorizado));

        event(new ReservedListAuthorizedChanged('refresh', $cotizacion->id, $user->USERID));
    }
}
