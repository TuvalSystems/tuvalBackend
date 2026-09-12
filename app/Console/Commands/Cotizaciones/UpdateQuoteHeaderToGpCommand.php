<?php

namespace App\Console\Commands\Cotizaciones;

use App\Jobs\Venta\Cotizaciones\SyncGpQuoteJob;
use App\Services\Imports\Contracts\QuoteImportsInterface;
use Carbon\Carbon;
use Illuminate\Console\Command;

class UpdateQuoteHeaderToGpCommand extends Command
{

    protected $signature = 'import:quote-mysql-history {start_date} {end_date}';
    protected $description = 'Migra datos históricos desde MySQL hacia SQL Server (GP) para evitar timeouts en la API';


    public function handle(QuoteImportsInterface $import)
    {
        $start  = Carbon::parse($this->argument('start_date'))->startOfDay();
        $end    = Carbon::parse($this->argument('end_date'))->startOfDay();

        if ($start->gt($end)) {
            $this->error('la fecha inicial no puede ser mayor que la fecha final.');
            return self::FAILURE;
        }
        while ($start->lte($end)) {
            $periodStart = $start->copy();
            $periodEnd = $start->copy()->endOfMonth();

            if ($periodEnd->gt($end)) {
                $periodEnd = $end->copy();
            }

            $fechaIni = $periodStart->copy()->startOfDay()->format('Y-m-d H:i:s');
            $fechaFin = $periodEnd->copy()->endOfDay()->format('Y-m-d H:i:s');
            $this->info("Encolando periodo: $fechaIni al $fechaFin");

            $import->getQuotesByUpdate($fechaIni, $fechaFin)
                ->chunkById(500, function ($quotes) use ($import) {
                    $ids = $quotes->pluck('id')->values()->toArray();

                    if (empty($ids)) {
                        return;
                    }

                    $marked = $import->markQuotesAsInProcess($ids);

                    if ($marked === 0) {
                        return;
                    }

                    $payload = $quotes->map(function ($quote) {
                        return [
                            'id' => $quote->id,
                            'cotizacion' => $quote->cotizacion,
                            'estado' => $quote->estado,
                        ];
                    })->values()->toArray();

                    SyncGpQuoteJob::dispatch($payload)->onQueue('sync-low');
                    $this->info('Encolado chunk de cotizaciones: ' . count($payload));
                });

            $start = $periodEnd->copy()->addDay()->startOfDay();
        }

        $this->info("Todos los periodos han sido encolados.");
        return Command::SUCCESS;
    }
}
