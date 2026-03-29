<?php

namespace App\Console\Commands\Devoluciones;

use App\Jobs\Devoluciones\EnqueueDevoluciones;
use App\Models\devoluciones\GpDevolucionErroresJob;
use App\Services\Devoluciones\DevolucionJobService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcesarDevolucionesCommand extends Command
{

    protected $devolucionesService;
    protected $signature = 'devoluciones:procesar';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Procesar todas las devoluciones basadas en los criterios establecidos';

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
    public function handle(DevolucionJobService $devolucionJobService)
    {

        Log::channel('devolucion')->info('▶️ Encolando procesamiento de devoluciones...');
        EnqueueDevoluciones::dispatch()->onQueue('devoluciones');
        $this->info('🧾 Devoluciones encoladas. El procesamiento lo hacen los workers.');
        return 0;


    }



 
}
