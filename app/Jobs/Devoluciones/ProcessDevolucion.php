<?php

namespace App\Jobs\Devoluciones;

use App\Models\devoluciones\GpDevolucionErroresJob;
use App\Models\devoluciones\GpDevoluciones;
use App\Services\Devoluciones\DevolucionJobService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Bus\Batchable;

class ProcessDevolucion implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Batchable;


    public array|int $backoff = [10, 30, 60, 120]; // backoff exponencial simple
    public int $tryAfter = 5;

    public function __construct(public int $devolucionId)
    {
        $this->afterCommit = true;
        $this->onQueue('devoluciones');
    }

     public function uniqueId(): string
    {
        return 'devolucion:' . $this->devolucionId;
    }



    // Evita solapamiento de ejecución y limita throughput si hace falta
    public function middleware(): array
    {
        return [
            new WithoutOverlapping('devolucion:' . $this->devolucionId), // lock en Redis (requiere CACHE_DRIVER=redis)
            new RateLimited('devoluciones'),                              // opcional: define bucket 'devoluciones' en rate limiter
        ];
    }


    public function handle(DevolucionJobService $service)
    {
        if (GpDevolucionErroresJob::where('devolucion_id', $this->devolucionId)->exists()) {
            Log::channel('devolucion')->warning("⏭️ Devolución {$this->devolucionId} ya con error registrado. Saltando.");
            return;
        }

        try {
            $devolucion = GpDevoluciones::find($this->devolucionId);
            if (!$devolucion) {
                Log::channel('devolucion')->warning("❓ Devolución {$this->devolucionId} no encontrada.");
                return;
            }
            $service->procesarDevolucionJob($devolucion);
            Log::channel('devolucion')->info("✅ Devolución {$this->devolucionId} procesada.");
        } catch (\Throwable $e) {
            Log::channel('devolucion')->error("❌ Devolución {$this->devolucionId} error: " . $e->getMessage(), [
                'id' => $this->devolucionId,
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
}
