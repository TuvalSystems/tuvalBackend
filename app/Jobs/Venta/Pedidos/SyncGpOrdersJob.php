<?php

namespace App\Jobs\Venta\Pedidos;

use App\Enums\Admin\Sync\StatusSync;
use App\Models\Admin\SyncLog;
use App\Models\GpPedido;
use App\Models\GpPedidoDetalle;
use App\Models\GpUsuario;
use App\Notifications\User\UserNotification;
use App\Services\Imports\Contracts\OrdersImportInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Notification;
use Throwable;

class SyncGpOrdersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public $timeout = 900;
    public $tries = 1;
    public $failOnTimeout = true;
    public function __construct(
        protected string $type,
        protected array $records
    ) {}


    public function handle(OrdersImportInterface $import): void
    {

        if ($this->type === 'orders') {
            $import->updateSqlServerOrdersFromPayload($this->records);
            return;
        }

        if ($this->type === 'details') {
            $import->updateSqlServerOrderDetailsFromPayload($this->records);
            return;
        }

        throw new \InvalidArgumentException("Tipo de sincronización no válido: {$this->type}");
    }
    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function middleware(): array
    {

        $ids = collect($this->records)
            ->pluck('id')
            ->implode(',');

        $hash = md5($this->type . '|' . $ids);

        return [
            (new WithoutOverlapping("sync-gp-orders:{$this->type}:{$hash}"))
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

        if ($this->type === 'orders') {
            GpPedido::whereIn('id', $ids)
                ->where('import_job', 3)
                ->update([
                    'import_job' => 2,
                ]);
        }

        if ($this->type === 'details') {
            GpPedidoDetalle::whereIn('id', $ids)
                ->where('import_job', 3)
                ->update([
                    'import_job' => 2,
                ]);
        }

        $payloadLog = [
            'type'  => $this->type,
            'count' => count($this->records),
            'ids'   => $ids,
        ];
        SyncLog::logErrorSync(
            'SyncGpOrdersJob',
            'update_batch_orders',
            'updateSqlServerOrdersFromPayload - updateSqlServerOrderDetailsFromPayload',
            $exception->getMessage(),
            $payloadLog,
            null,
            json_encode($payloadLog, JSON_UNESCAPED_UNICODE),
            $this->attempts(),
            StatusSync::FAILED
        );
        $usersAlert = GpUsuario::whereIn('perfil_id', [1, 2])->get();
        Notification::send($usersAlert, (new UserNotification('Update Masivo Pedidos', $exception->getMessage())));
    }
}
