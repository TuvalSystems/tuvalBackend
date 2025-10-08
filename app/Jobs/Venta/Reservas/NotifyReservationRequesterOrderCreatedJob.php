<?php

namespace App\Jobs\Venta\Reservas;

use App\Models\GpUsuario;
use App\Notifications\Venta\Reservas\UserReservedNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class NotifyReservationRequesterOrderCreatedJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;


    public function __construct(public string $orderNumber, public int $userSolicitanteId)
    {
        Log::channel('redis_log')->info('NotifyReservationRequesterOrderCreatedJob boot', [
            'env_host' => config('database.redis.default.host'),
            'env_port' => config('database.redis.default.port'),
            'client'   => config('database.redis.client'),
            'queue_connection' => config('queue.default'),
        ]);

        $this->onQueue('notifications');
        $this->afterCommit = true;
    }


    public function handle()
    {
        $user = GpUsuario::find($this->userSolicitanteId);
        $title = 'Pedido Creado';
        $mensaje = "Se ha autorizado la reserva y se ha generado el Pedido {$this->orderNumber}";
        $user->notify(
            (new UserReservedNotification($title, $mensaje))
                ->onQueue('notifications')
        );
    }
}
