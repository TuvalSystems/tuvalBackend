<?php

namespace App\Console\Commands;

use App\Http\Controllers\WebserviceController;
use App\Services\Api\Devoluciones\ApiGpReturnsIntegrationService;
use App\Services\Devoluciones\DevolucionJobService;
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
        Log::info('✅ Comando devoluciones:procesar ejecutado a las ' . now());
        $this->info('🔄 Procesando devoluciones...');

        $devoluciones = $devolucionJobService->listarTodasLasDevolucionesAplicables();
        $total = $devoluciones->count();
        if ($total === 0) {
            $this->info('✅ No hay devoluciones pendientes por procesar.');
            return 0;
        }

        $bar = $this->output->createProgressBar($total);
        $bar->start();
        foreach ($devoluciones as $devolucion) {
             $devolucionJobService->procesarDevolucionJob($devolucion);
            $bar->advance();
        }
        $bar->finish();
        $this->newLine(2);
        $this->info('✅ Proceso de devoluciones completado exitosamente.');

        return 0;

    }
}
