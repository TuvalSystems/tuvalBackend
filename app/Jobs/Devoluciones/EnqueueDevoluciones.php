<?php

namespace App\Jobs\Devoluciones;

use App\Services\Devoluciones\DevolucionJobService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;

class EnqueueDevoluciones implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;


    public int $chunkSize;
    public function __construct(int $chunkSize = 200)
    {
        $this->chunkSize = $chunkSize;
        $this->onQueue('devoluciones');
         $this->afterCommit = true;
    }

    public function handle(DevolucionJobService $service)
    {
          // 1) Trae las devoluciones aplicables (tu método actual)
        $devoluciones = $service->listarTodasLasDevolucionesAplicables();
        $devoluciones->chunk($this->chunkSize)->each(function ($chunk){
           Log::channel('devolucion')->info("Se encontraron {$chunk->count()} devoluciones para procesar");
            $batch = [];
            foreach ($chunk as $dev) {
                $batch[] = (new ProcessDevolucion($dev->id))->onQueue('devoluciones');
            }
            if ($batch) {
                Bus::batch($batch)
                ->name('devoluciones:procesar')
                ->onQueue('devoluciones')
                ->allowFailures()
                ->dispatch();
            }
        });
    }
}
