<?php

namespace App\Jobs\Venta\Cotizaciones;

use App\Models\GpUsuario;
use App\Notifications\Venta\Cotizaciones\UserReasonNoSalesNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class NotifyUnsoldQuotesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public string $title, public string $msg, public array $data , public int $userId)
    {
          $this->onQueue('notifications');
    }

    public function handle()
    {
        $user = GpUsuario::find($this->userId);
        $user->notify(
            (new UserReasonNoSalesNotification($this->title, $this->msg, $this->data))->onQueue('notifications')
        );
    }
}
