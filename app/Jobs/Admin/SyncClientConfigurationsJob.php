<?php

namespace App\Jobs\Admin;

use App\Enums\Admin\Sync\StatusSync;
use App\Exceptions\ClientConfigurationSyncException;
use App\Models\Admin\Sync\SyncLog;
use App\Services\Integraciones\Sincronizacion\Contracts\SyncClientsServiceInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class SyncClientConfigurationsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 120;
    public $failOnTimeout = true;
    public $tries = 4;
    public array|int $backoff = [10, 30, 60, 120];

    public function __construct(public int $batchSize = 1000, public ?int $maxBatches = null)
    {
        $this->onQueue('sync-medium');
    }

    /**
     * Execute the job.
     */
    public function handle(SyncClientsServiceInterface $service): void
    {
        $service->syncClientConfigurations($this->batchSize, $this->maxBatches);
    }

    public function failed(Throwable $exception): void
    {
        $context = $exception instanceof ClientConfigurationSyncException ? $exception->syncContext : [];
        SyncLog::logErrorSync(
            'SyncClientConfigurationsJob',
            'sincronizar_clientes_configuraciones',
            'syncClientConfigurations',
            $exception->getMessage(),
            data_get($context, 'clave.txCli'),
            null,
            array_merge(['batchSize' => $this->batchSize, 'maxBatches' => $this->maxBatches], $context),
            $this->attempts(),
            StatusSync::FAILED
        );
    }
}
