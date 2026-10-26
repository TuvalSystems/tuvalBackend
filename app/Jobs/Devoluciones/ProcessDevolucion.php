<?php

namespace App\Jobs\Devoluciones;

use App\Models\Devoluciones\GpDevolucionErroresJob;
use App\Models\Devoluciones\GpDevoluciones;
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
use Illuminate\Contracts\Cache\Repository;

class ProcessDevolucion implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Batchable;

    public $timeout = 300;
    public $failOnTimeout = true;
    public array|int $backoff = [10, 30, 60, 120]; // backoff exponencial simple

    public function __construct(public int $devolucionId)
    {
        $this->afterCommit = true;
        $this->onQueue('devoluciones');
    }

    public function uniqueId(): string
    {
        return 'devolucion:' . $this->devolucionId;
    }

    public function uniqueVia(): Repository
    {
        return cache()->driver('redis');
    }

    public function uniqueFor()
    {
        // El bloqueo de unicidad debe durar al menos lo mismo que el timeout
        return 310;
    }



    // Evita solapamiento de ejecución y limita throughput si hace falta
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('devolucion:' . $this->devolucionId))
                ->expireAfter(310)
                ->dontRelease(),
            new RateLimited('devoluciones')
        ];
    }


    public function handle(DevolucionJobService $service)
    {
        if (GpDevolucionErroresJob::where('devolucion_id', $this->devolucionId)->exists()) {
            return;
        }
        try {
            $devolucion = GpDevoluciones::find($this->devolucionId);
            if (!$devolucion) {
                return;
            }
            $service->procesarDevolucionJob($devolucion);
        } catch (\Throwable $e) {
            Log::channel('devolucion')->error("❌ Devolución {$this->devolucionId} error: " . $e->getMessage(), [
                'id' => $this->devolucionId,
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
}
