<?php

namespace App\Jobs\Aprobaciones;

use App\Models\Admin\Usuarios\GpUsuario;
use App\Notifications\User\UserNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class NotifyApprovalOrdersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public function __construct(public string $title, public string $msg, public int $userId)
    {
        $this->onQueue('notifications');
        $this->afterCommit = true;
    }

    public function handle()
    {
        $user = GpUsuario::find($this->userId);
        $user->notify(new UserNotification($this->title, $this->msg));
    }
}
