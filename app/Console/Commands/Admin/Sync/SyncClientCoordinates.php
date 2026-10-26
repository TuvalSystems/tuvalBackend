<?php

namespace App\Console\Commands\Admin\Sync;

use App\Jobs\Admin\SyncClientCoordinatesJob;
use App\Services\Integraciones\Sincronizacion\Contracts\SyncClientsServiceInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncClientCoordinates extends Command
{
    protected $signature = 'sync:clients-coordinates';
    protected $description = 'Proceso de sincronización de coordenadas de clientes';
    public function handle(SyncClientsServiceInterface $service)
    {
        $this->info('Iniciando proceso de sincronización de coordenadas de clientes');
        $rawCood = $service->getClientsCoordinates();
        if (empty($rawCood)) {
            $this->info('Nada que sincronizar.');
            return 0;
        }

        foreach ($rawCood as $cord) {
            SyncClientCoordinatesJob::dispatchSync($cord);
        }

        Log::channel('cron_process')->info('Se encontraron ' . count($rawCood) . ' coordenadas');
        $this->info('Clientes enviados a la cola de Redis.');
        return 0;
    }
}
