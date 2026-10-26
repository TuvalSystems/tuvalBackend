<?php

namespace App\Jobs\Inventario\Articulos;

use App\Enums\Admin\Sync\StatusSync;
use App\Models\Admin\Sync\SyncLog;
use App\Models\Inventario\Articulos\GpArticulo;
use App\Models\Admin\Usuarios\GpUsuario;
use App\Notifications\User\UserNotification;
use App\Services\Inventario\Articulo\Contracts\ArticleSyncServiceInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Notification;
use Throwable;

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

    public function failed(Throwable $exception)
    {
        $ids = $this->articles
            ->pluck('id')
            ->sort()
            ->implode(',');
        GpArticulo::whereIn('id', $ids)->update([
            'import_job' => 2
        ]);

        $payloadLog = [
            'count' => count($this->articles),
            'ids'   => $ids,
        ];
        SyncLog::logErrorSync(
            'SyncGpArticlesJob',
            'sync_articles_classification',
            'updateArticlesFromSqlServer',
            $exception->getMessage(),
            $payloadLog,
            null,
            json_encode($payloadLog, JSON_UNESCAPED_UNICODE),
            $this->attempts(),
            StatusSync::FAILED
        );
        $usersAlert = GpUsuario::whereIn('perfil_id', [1, 2])->get();
        Notification::send($usersAlert, (new UserNotification('Update Masivo Clasificación Articulos', $exception->getMessage())));
    }
}
