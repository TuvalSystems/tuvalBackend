<?php

namespace App\Console\Commands\Admin\Sync;

use App\Jobs\Admin\SyncClientBranchesJob;
use App\Services\Admin\Sync\Contracts\SyncClientsServiceInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncClientBranches extends Command
{
    protected $signature = 'sync:client-branches';
    protected $description = 'Proceso de sincronización de sucursales de clientes desde GP';

    public function handle(SyncClientsServiceInterface $service)
    {
        $this->info('Iniciando proceso de sincronización de sucursales de clientes');
        $fechaFiltro = now()->subMinutes(5)->format('Y-m-d H:i:s');
        $rawBranches = $service->getBranchesGp($fechaFiltro);
        if (empty($rawBranches)) {
            $this->info('Nada que sincronizar.');
            return 0;
        }
        foreach ($rawBranches as $branch) {
            //  SyncClientBranchesJob::dispatchSync($branch);
            SyncClientBranchesJob::dispatch($branch);
        }
        $count = count($rawBranches);
        Log::channel('cron_process')->info('Se encontraron ' . $count . ' sucursales');
    }
}
