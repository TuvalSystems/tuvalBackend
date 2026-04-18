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

class SyncClientJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $client;
    public $timeout = 120;
    public $failOnTimeout = true;
    public $tries = 4;
    public array|int $backoff = [10, 30, 60, 120]; // backoff exponencial simple


    //sync-high
    //sync-medium
    //sync-low

    public function __construct(array $client)
    {
        $this->client = $client;
        $this->onQueue('sync-high');
    }


    public function handle(SyncClientsServiceInterface $service)
    {
        $service->handleClientSync($this->client);
    }


    public function failed(Throwable $exception)
    {
        SyncLog::logErrorSync(
            'SyncClientJob',
            'sincronizar_clientes',
            'handleClientSync',
            $exception->getMessage(),
            $this->client['ruc'],
            null,
            json_encode($this->client),
            $this->attempts(),
            StatusSync::FAILED
        );
        $usersAlert = GpUsuario::whereIn('perfil_id', [1, 2])->get();
        Notification::send($usersAlert, (new UserNotification('Error definitivo: Sincronización Clientes', $exception->getMessage())));
    }
}
