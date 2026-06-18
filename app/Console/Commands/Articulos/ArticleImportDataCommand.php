<?php

namespace App\Console\Commands\Articulos;

use App\Jobs\Inventario\Articulos\SyncGpArticlesJob;
use App\Services\Inventario\Articulo\Contracts\ArticleSyncServiceInterface;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ArticleImportDataCommand extends Command
{

    protected $signature = 'import:update-articles-gp {start_date} {end_date}';


    protected $description = 'Importa datos actualizados de gp a mysql ';


    public function handle(ArticleSyncServiceInterface $service)
    {
        $start  = Carbon::parse($this->argument('start_date'))->startOfDay();
        $end    = Carbon::parse($this->argument('end_date'))->startOfDay();
        if ($start->gt($end)) {
            $this->error('La fecha inicial no puede ser mayor que la fecha inicial');
        }
        while ($start->lte($end)) {
            $periodStart    = $start->copy();
            $periodEnd      = $start->copy()->endOfMonth();

            if ($periodEnd->gt($end)) {
                $periodEnd = $end->copy();
            }
            $fechaIni = $periodStart->copy()->startOfDay()->format('Y-m-d H:i:s');
            $fechaFin = $periodEnd->copy()->endOfDay()->format('Y-m-d H:i:s');
            $this->info("Encolando artuculos: $fechaIni al $fechaFin");
            $service->getArticlesWithoutUpdateQuery($fechaIni, $fechaFin)
                ->chunkById(200, function ($articles) use ($service) {
                    $ids = $articles->pluck('id')->values()->toArray();
                    if (empty($ids)) {
                        return;
                    }
                    // $service->markArticleInProgress($ids);
                    // SyncGpArticlesJob::dispatchSync($articles);
                    SyncGpArticlesJob::dispatch($articles)->onQueue('sync-low');
                    $this->info('Encolando chuck de articulos' . $articles->count());
                });
            $start = $periodEnd->copy()->addDay()->startOfDay();
        }
        $this->info("Todos los periodos han sido encolados.");
        return self::SUCCESS;
    }
}
