<?php

namespace App\Jobs\Venta\Reservas;

use App\Models\GpUsuario;
use App\Notifications\User\UserNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class NotifyReceivedReservationsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public string $title, public string $msg, public  int $userIngresoId)
    {
        $this->onQueue('notifications');
        $this->afterCommit = true;
    }

    public function handle()
    {
        $user = GpUsuario::find($this->userIngresoId);
        $user->notify(
            (new UserNotification($this->title, $this->msg))
            // ->onQueue('notifications')
        );
    }
}
