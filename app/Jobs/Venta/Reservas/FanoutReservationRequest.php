<?php

namespace App\Jobs\Venta\Reservas;

use App\Events\Venta\Reservas\ReservationApprovalsChanged;
use App\Models\GpCotizacion;
use App\Models\GpUsuario;
use App\Notifications\Venta\Reservas\ReservationRequestNotification;
use App\Notifications\Venta\Reservas\WarehouseRequestNotificaction;
use Illuminate\Bus\Queueable;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Notification;

class FanoutReservationRequest implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;



    public function __construct(public int $cotizacionId, public int $actorId)
    {
        $this->onQueue('notifications');   // cola por defecto de este Job
        $this->onConnection('database');   // conexión de colas
        $this->afterCommit = true;
    }

    public function handle()
    {
        $cotizacion = GpCotizacion::select('id', 'cotizacion')->find($this->cotizacionId);
        $actor      = GpUsuario::find($this->actorId);
        if (!$cotizacion || !$actor) return;

        $this->sendNotifyUsersApproval($cotizacion, $actor);
        $this->sendNotifyUsersWarehouse($cotizacion, $actor);

        // Broadcast global para refrescar grillas de aprobaciones
        event(new ReservationApprovalsChanged('solicitud', $cotizacion->id, $actor->USERID));
    }


    public function sendNotifyUsersApproval($cotizacion, $actor)
    {
        // Destinatarios: perfil 9/2 activos (ajusta campos según tu schema)
        $usersApproval = GpUsuario::query()
            ->whereIn('perfil_id', [9, 2])
            ->where('status', 1)
            ->whereKeyNot($actor->id) //comentado para que a mi mismo me llegue la notificación
            ->get();
        if ($usersApproval->isNotEmpty()) {
            Notification::send(
                $usersApproval,
                new ReservationRequestNotification(
                    'Nueva solicitud de reserva de inventario',
                    $cotizacion->cotizacion,
                    $actor
                )
            );
        }
    }


    public function sendNotifyUsersWarehouse($cotizacion, $actor)
    {
        $usersWarehouses = GpUsuario::query()
            ->whereIn('perfil_id', [13, 2])
            ->where('status', 1)
            ->whereKeyNot($actor->id) //comentado para que a mi mismo me llegue la notificación
            ->get();

        if ($usersWarehouses->isNotEmpty()) {
            Notification::send(
                $usersWarehouses,
                new WarehouseRequestNotificaction(
                    'Reserva de inventario',
                    $cotizacion->cotizacion,
                    $actor
                )
            );
        }
    }
}
