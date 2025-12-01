<?php

namespace App\Jobs\Venta\Cotizaciones;

use App\Models\GpUsuario;
use App\Notifications\User\UserNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class NotifyUnsoldQuotesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public string $title, public string $msg, public int $userId)
    {
          $this->onQueue('notifications');
    }

    public function handle()
    {
        $user = GpUsuario::find($this->userId);
        $user->notify(
            (new UserNotification($this->title, $this->msg))->onQueue('notifications')
        );
    }
}
