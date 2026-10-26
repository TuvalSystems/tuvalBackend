<?php

namespace App\Jobs\Cobranzas\CobrosFacturas;

use App\Enums\Admin\Sync\StatusSync;
use App\Models\Admin\Sync\SyncLog;
use App\Models\Cobranzas\Cobros\GpCobro;
use App\Services\Cobranzas\CobrosFacturas\Contracts\CollectionGpSenderServiceInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Throwable;

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
        $this->onQueue('sync-high');
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

    public function failed(Throwable $exception): void
    {
        $collection = GpCobro::query()
            ->select(['id', 'numero_recibo', 'cliente_id', 'user_id', 'estado'])
            ->find($this->collectionId);

        $payloadLog = [
            'collection_id' => $this->collectionId,
            'numero_recibo' => $collection?->numero_recibo,
            'cliente_id'    => $collection?->cliente_id,
            'user_id'       => $collection?->user_id,
            'estado'        => $collection?->estado,
            'queue'         => 'sync-high',
        ];

        SyncLog::logErrorSync(
            'SendCollectionToGpJob',
            'enviar_cobro_gp',
            'sendCollectionToGp',
            $exception->getMessage(),
            $collection?->numero_recibo,
            $this->collectionId,
            json_encode($payloadLog, JSON_UNESCAPED_UNICODE),
            $this->attempts(),
            StatusSync::FAILED
        );
    }
}
