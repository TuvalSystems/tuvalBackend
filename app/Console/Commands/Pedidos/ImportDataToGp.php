<?php

namespace App\Console\Commands\Pedidos;

use App\Jobs\Venta\Pedidos\SyncGpOrdersJob;
use App\Services\Imports\Contracts\OrdersImportInterface;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ImportDataToGp extends Command
{
    protected $signature = 'import:orders-mysql-history {start_date} {end_date}';
    protected $description = 'Migra datos históricos desde MySQL hacia SQL Server (GP) para evitar timeouts en la API';


    public function handle(OrdersImportInterface $import)
    {
        $start = Carbon::parse($this->argument('start_date'));
        $end = Carbon::parse($this->argument('end_date'));
        while ($start->lte($end)) {
            $fechaIni = $start->copy()->format('Y-m-d 00:00:00');
            $fechaFin = $start->copy()->endOfMonth()->lte($end)
                ? $start->copy()->endOfMonth()->format('Y-m-d 23:59:59')
                : $end->format('Y-m-d 23:59:59');
            $this->info("Encolando periodo: $fechaIni al $fechaFin");

            SyncGpOrdersJob::dispatch($fechaIni, $fechaFin)->onQueue('migration_local_history');
            $start->addMonthNoOverflow();
        }
        $this->info("Todos los periodos han sido encolados.");
    }
}
