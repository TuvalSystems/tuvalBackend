<?php

namespace App\Console\Commands\Admin\Sync;

use Illuminate\Console\Command;

class SyncClients extends Command
{

    protected $signature = 'sync:clients';


    protected $description = 'Proceso de sincronización de clientes';


    public function handle()
    {
        $this->info('Iniciando proceso de despacho de clientes');
        $fechaFiltro = now()->subMinutes(30)->format('Y-m-d H:i:s');
        dd($fechaFiltro);

    }
}
