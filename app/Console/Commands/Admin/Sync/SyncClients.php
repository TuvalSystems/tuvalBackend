<?php

namespace App\Console\Commands\Admin\Sync;

use App\Jobs\Admin\SyncClientJob;
use App\Services\Admin\Sync\Contracts\SyncClientsServiceInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncClients extends Command
{

    protected $signature = 'sync:clients';


    protected $description = 'Proceso de sincronización de clientes';


    public function handle(SyncClientsServiceInterface $service)
    {
        $this->info('Iniciando proceso de despacho de clientes');
        // $fechaFiltro = now()->subMinutes(30)->format('Y-m-d H:i:s');
        $fechaFiltro = '2025-04-20 00:00:00';
        $rawClients = $service->getClientsGp($fechaFiltro);
        if (empty($rawClients)) {
            $this->info('Nada que sincronizar.');
            return 0;
        }

        foreach ($rawClients as $client) {
            SyncClientJob::dispatch($client);
        }
        Log::channel('cron_process')->info('Se encontraron ' . count($rawClients) . ' clientes');
        $this->info('Clientes enviados a la cola de Redis.');
        return 0;
    }
}
