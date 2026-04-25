<?php

namespace App\Console\Commands\Admin\Sync;

use App\Jobs\Admin\SyncClientJob;
use App\Services\Admin\Sync\Contracts\SyncClientsServiceInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncClients extends Command
{

    protected $signature = 'sync:clients {--date= : Fecha opcional para sincronizar (ej. YYYY-MM-DD HH:ii:ss)}';


    protected $description = 'Proceso de sincronización de clientes';


    public function handle(SyncClientsServiceInterface $service)
    {
        $this->info('Iniciando proceso de despacho de clientes');
        $inputDate = $this->option('date');
        if ($inputDate) {
            $fechaFiltro = $inputDate;
            $this->info("Modo manual activado. Filtrando desde: {$fechaFiltro}");
        } else {
            // Lógica por defecto (la que se ejecuta en tu Cron Job / Scheduler)
            $fechaFiltro = now()->subMinutes(30)->format('Y-m-d H:i:s');
            $this->info("Modo automático. Filtrando desde: {$fechaFiltro}");
        }
        // $fechaFiltro = now()->subMinutes(30)->format('Y-m-d H:i:s');
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
