<?php

namespace App\Jobs\Venta\Reservas;

use App\Events\Venta\Reservas\ReservedListAuthorizedChanged;
use App\Models\GpUsuario;
use App\Models\GpCotizacion;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Notifications\Venta\Reservas\ApprovalReservationNotification;

class SendApprovalReservationNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

   public function __construct(public int $cotizacionId, public int $autorizadoId)
    {
        $this->onQueue('notifications');   // cola por defecto de este Job
        $this->onConnection('database');   // conexión de colas
        $this->afterCommit = true;
    }


    public function handle()
    {
        $cotizacion = GpCotizacion::select('id', 'cotizacion','co_usrIngSolResInv')->whereKey($this->cotizacionId)
            ->first();
        if (!$cotizacion) {
            return;
        }
        $user = GpUsuario::find($cotizacion->co_usrIngSolResInv);
        $userAutorizado = GpUsuario::find($this->autorizadoId);
        $user->notify(new ApprovalReservationNotification('Reserva Aprobada', $cotizacion->cotizacion, $userAutorizado));

        event(new ReservedListAuthorizedChanged('refresh', $cotizacion->id, $user->USERID));

    }
}
