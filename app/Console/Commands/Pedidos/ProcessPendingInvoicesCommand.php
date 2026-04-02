<?php

namespace App\Console\Commands\Pedidos;

use App\Services\Venta\OrderJobService;
use Illuminate\Console\Command;

class ProcessPendingInvoicesCommand extends Command
{

    protected $signature = 'pedidos:facturar-enviando';


    protected $description = 'Procesar las facturas en estado enviando';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(OrderJobService $jobService)
    {
         $this->info('🔄 Enviando Pedidos a Facturar...');
         $jobService->processPendingInvoices();
    }
}
