<?php

namespace App\Jobs\Venta\Cotizaciones;

use App\Enums\Admin\Sync\StatusSync;
use App\Models\Admin\SyncLog;
use App\Models\GpCotizacion;
use App\Models\GpUsuario;
use App\Notifications\User\UserNotification;
use App\Services\Imports\Contracts\QuoteImportsInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Notification;
use Throwable;

class SyncGpQuoteJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public $timeout = 900;
    public $tries = 1;
    public $failOnTimeout = true;

    public function __construct(protected array $records) {}


    public function handle(QuoteImportsInterface $import): void
    {
        $import->updateSqlServerQuotesFromPayload(collect($this->records));
    }

    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function middleware(): array
    {

        $ids = collect($this->records)
            ->pluck('id')
            ->filter()
            ->implode(',');

        $hash = md5($ids . '|' . count($this->records));

        return [
            (new WithoutOverlapping("sync-gp-quotes-status:{$hash}"))
                ->releaseAfter(60)
                ->expireAfter(7500),
        ];
    }

    public function failed(Throwable $exception): void
    {
        $ids = collect($this->records)
            ->pluck('id')
            ->values()
            ->toArray();

        GpCotizacion::whereIn('id', $ids)
            ->where('import_job', 3)
            ->update([
                'import_job' => 2,
            ]);



        $payloadLog = [
            'count' => count($this->records),
            'ids'   => $ids,
        ];
        SyncLog::logErrorSync(
            'SyncGpQuoteJob',
            'update_batch_quotes_status',
            'updateSqlServerQuotesFromPayload',
            $exception->getMessage(),
            $payloadLog,
            null,
            json_encode($payloadLog, JSON_UNESCAPED_UNICODE),
            $this->attempts(),
            StatusSync::FAILED
        );
        $usersAlert = GpUsuario::whereIn('perfil_id', [1, 2])->get();
        Notification::send($usersAlert, (new UserNotification('Update Masivo Cotizaciones', $exception->getMessage())));
    }
}
