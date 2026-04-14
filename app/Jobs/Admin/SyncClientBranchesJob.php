<?php

namespace App\Jobs\Admin;

use App\Enums\Admin\Sync\StatusSync;
use App\Models\Admin\SyncLog;
use App\Models\GpUsuario;
use App\Notifications\User\UserNotification;
use App\Services\Admin\Sync\Contracts\SyncClientsServiceInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Notification;

use Throwable;

class SyncClientBranchesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $branch;
    public $timeout = 120;
    public $failOnTimeout = true;
    public $tries = 4;
    public array|int $backoff = [10, 30, 60, 120]; // backoff exponencial simple


    public function __construct(array $branch)
    {
        $this->branch = $branch;
        // $this->queue = 'sync-medium';
        $this->onQueue('sync-medium');
    }

    public function handle(SyncClientsServiceInterface $service)
    {
        $service->handleClientBranchSync($this->branch);
    }

    public function failed(Throwable $exception)
    {
        SyncLog::logErrorSync(
            'SyncClientBranchesJob',
            'sincronizar_clientes_sucursales',
            'handleClientBranchSync',
            $exception->getMessage(),
            $this->branch['cliente'],
            null,
            json_encode($this->branch),
            $this->attempts(),
            StatusSync::FAILED
        );
        $usersAlert = GpUsuario::whereIn('perfil_id', [1, 2])->get();
        Notification::send($usersAlert, (new UserNotification('Error definitivo: Sincronización Clientes', $exception->getMessage())));
    }
}
