<?php

namespace App\Jobs\Admin;

use App\Enums\Admin\Sync\StatusSync;
use App\Models\Admin\Sync\SyncLog;
use App\Models\Admin\Usuarios\GpUsuario;
use App\Notifications\User\UserNotification;
use App\Services\Admin\Sync\Contracts\SyncClientsServiceInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Notification;
use Throwable;

class SyncClientCoordinatesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $coordinate;
    public $timeout = 120;
    public $failOnTimeout = true;
    public $tries = 4;
    public array|int $backoff = [10, 30, 60, 120];
    public function __construct(array $coordinate)
    {
        $this->coordinate = $coordinate;
        $this->onQueue('sync-high');
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(SyncClientsServiceInterface $service)
    {
        $service->handleClientBranchCoordinatesSync($this->coordinate);
    }


     public function failed(Throwable $exception)
    {
        SyncLog::logErrorSync(
            'SyncClientCoordinatesJob',
            'sincronizar_clientes_coordenadas',
            'handleClientBranchCoordinatesSync',
            $exception->getMessage(),
            $this->coordinate['idCliente'],
            null,
            $this->coordinate,
            $this->attempts(),
            StatusSync::FAILED
        );
        $usersAlert = GpUsuario::whereIn('perfil_id', [1, 2])->get();
        Notification::send($usersAlert, (new UserNotification('Sincronización de Coordenadas ', $exception->getMessage())));
    }
}
