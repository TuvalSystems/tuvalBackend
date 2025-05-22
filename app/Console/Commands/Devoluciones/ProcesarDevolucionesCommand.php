<?php

namespace App\Console\Commands\Devoluciones;

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
        Log::info('✅ [ProcessDevolucionesJob] - Command executed at ' . now());
        $this->info('🔄 Procesando devoluciones...');

        $startTime = Carbon::now();
        $devoluciones = $devolucionJobService->listarTodasLasDevolucionesAplicables();
        $total = $devoluciones->count();

        // $cl = collect($devoluciones)->pluck('id')->toArray();
        // dd($cl, $total);

        if ($total === 0) {
            $this->info('✅ No hay devoluciones pendientes por procesar.');
            return 0;
        }

        $this->info("🔎 Found {$total} devoluciones to process.");
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $processed = 0;
        $errors = 0;
        foreach ($devoluciones as $devolucion) {
            if ($this->hasProcessingError($devolucion)) {
                Log::warning("⏸️ Devolución con ID {$devolucion->id} ya tiene un error registrado. No se procesa.");
                continue;
            }
            if ($this->processDevolucion($devolucion, $devolucionJobService)) {
                $processed++;
            } else {
                $errors++;
            }
            $bar->advance();
        }
        $bar->finish();
        $endTime = Carbon::now();
        $duration = $startTime->diffInSeconds($endTime);
        $this->generateFinalLog($processed, $total, $total - $processed, $duration);

        $this->newLine(2);
        $this->info('✅ Proceso de devoluciones completado exitosamente.');

        return 0;
    }



    public function hasProcessingError($devolucion): bool
    {
        return GpDevolucionErroresJob::where('devolucion_id', $devolucion->id)->exists();
    }

    public function processDevolucion($devolucion, $devolucionJobService)
    {

        try {
            $devolucionJobService->procesarDevolucionJob($devolucion);
            Log::info("✔️ [ProcessDevolucionesJob] - Devolución ID: {$devolucion->id} processed successfully.");
            return true;
        } catch (\Throwable $e) {
            Log::error("❌ [ProcessDevolucionesJob] - Error processing Devolución ID: {$devolucion->id}. Error: " . $e->getMessage());
            return false;
        }
    }

    public function generateFinalLog(int $processed, int $total, int $errors, int $duration)
    {
        Log::info("🕒 [ProcessDevolucionesJob] - Process finished.");
        Log::info("📊 Total processed: {$processed}");
        Log::info("⚠️ Total errors: {$errors}");
        Log::info("⏱️ Duration: {$duration} seconds");
    }
}
