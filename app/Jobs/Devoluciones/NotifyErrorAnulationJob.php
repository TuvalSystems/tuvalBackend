<?php

namespace App\Jobs\Devoluciones;

use App\Models\Admin\Usuarios\GpUsuario;
use App\Notifications\User\UserNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class NotifyErrorAnulationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public function __construct(public int $userId, public string $msg)
    {
          $this->onQueue('notifications');
    }
    public function handle()
    {
        $user = GpUsuario::find($this->userId);
         $user->notify(
            (new UserNotification('Error al anular la devolucion', $this->msg))->onQueue('notifications')
        );
    }
}
