<?php

namespace App\Jobs\Venta\Pedidos;

use App\Models\GpPedido;
use App\Services\Imports\Contracts\OrdersImportInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncGpOrdersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public $timeout = 0; // 0 significa que no tiene límite de tiempo (ideal para procesos de millones en local)
    public $tries = 3;
    public function __construct(
        protected string $fechaIni,
        protected string $fechaFin
    ) {}


    public function handle(OrdersImportInterface $import)
    {

        // $import->updateDataSqlServerOrdersAndDetail($this->fechaIni, $this->fechaFin);
    }
}
