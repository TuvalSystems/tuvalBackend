<?php

namespace App\Jobs\Cobranzas\CobrosFacturas;

use App\Services\Cobranzas\CobrosFacturas\Contracts\CollectionGpSenderServiceInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;

class SendCollectionToGpJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;
    public int $tries = 3;
    public bool $failOnTimeout = true;
    public array $backoff = [60, 180, 300];

    public function __construct(public int $collectionId)
    {
        $this->afterCommit = true;
        $this->onQueue('sync-now');
    }

    public function uniqueId(): string
    {
        return 'cobro:' . $this->collectionId . ':envio-gp';
    }

    public function uniqueVia(): Repository
    {
        return cache()->driver('redis');
    }

    public function uniqueFor(): int
    {
        return 900;
    }

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping($this->uniqueId()))
                ->releaseAfter(60)
                ->expireAfter(900),
        ];
    }

    public function handle(CollectionGpSenderServiceInterface $sender): void
    {
        $sender->sendCollectionToGp($this->collectionId);
    }
}
