<?php

namespace App\Console\Commands\Devoluciones;

use App\Jobs\Devoluciones\EnqueueDevoluciones;
use App\Services\Devoluciones\DevolucionJobService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcesarDevolucionesCommand extends Command
{
    protected $signature = 'devoluciones:procesar';
    protected $description = 'Procesar todas las devoluciones basadas en los criterios establecidos';
    public function __construct()
    {
        parent::__construct();
    }
    public function handle(DevolucionJobService $devolucionJobService)
    {
        Log::channel('devolucion')->info('▶️ Encolando procesamiento de devoluciones...');
        EnqueueDevoluciones::dispatch()->onQueue('devoluciones');
        $this->info('🧾 Devoluciones encoladas. El procesamiento lo hacen los workers.');
        return 0;
    }
}
