<?php

namespace App\Jobs\Inventario\Articulos;

use App\Services\Inventario\Articulo\Contracts\ArticleSyncServiceInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;

class SyncGpArticlesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 900;
    public $tries = 1;
    public $failOnTimeout = true;
    public function __construct(protected Collection $articles) {}


    public function handle(ArticleSyncServiceInterface $service)
    {

        $service->updateArticlesFromSqlServer($this->articles);
    }

    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function middleware(): array
    {

        $ids = collect($this->articles)
            ->pluck('id')
            ->implode(',');

        $hash = md5($ids);

        return [
            (new WithoutOverlapping("sync-gp-articles:{$hash}"))
                ->releaseAfter(60)
                ->expireAfter(7500),
        ];
    }
}
