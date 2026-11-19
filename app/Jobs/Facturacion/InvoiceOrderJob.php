<?php

namespace App\Jobs\Facturacion;

use App\Models\Facturacion\InvoiceRequest;
use App\Enums\Facturacion\InvoiceRequestStatus;

use App\Services\Facturacion\Contracts\InvoiceServiceInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

class InvoiceOrderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $timeout = 900;
    public int $tries = 5;
    public bool $failOnTimeout = true;
    public array $backoff = [30, 120, 300, 600];

    public function __construct(public int $solicitudId, public int $version)
    {
        $this->onConnection('facturacion')->onQueue('facturacion');
        $this->beforeCommit(); // Cola database: publicación atómica con la transacción local.
    }

    public function handle(InvoiceServiceInterface $invoice): void
    {
        $request = $this->request()->first();
        if (!$request || !in_array($request->estado, [InvoiceRequestStatus::PENDING->value, InvoiceRequestStatus::PROCESSING->value, InvoiceRequestStatus::ERROR->value], true)) {
            return;
        }
        $invoice->executeWithOrderInvoiceLock($request->pedido_id, function () use ($invoice) {
            $request = $this->request()->first();
            if (!$request || $request->estado === InvoiceRequestStatus::COMPLETED->value) return;
            $this->request()->update(['estado' => InvoiceRequestStatus::PROCESSING->value, 'updated_at' => now()]);
            try {
                $invoice->processQueuedInvoice($request->pedido_id, (bool) $request->trasladado,
                    (bool) $request->check_demanda, (bool) $request->directa);
                $this->request()->update(['estado' => InvoiceRequestStatus::COMPLETED->value, 'ultimo_error' => null, 'updated_at' => now()]);
            } catch (Throwable $e) {
                $this->request()->update(['ultimo_error' => $e->getMessage(), 'updated_at' => now()]);
                throw $e;
            }
        });
    }

    public function failed(Throwable $exception): void
    {
        $this->request()->update(['estado' => InvoiceRequestStatus::ERROR->value, 'ultimo_error' => $exception->getMessage(), 'updated_at' => now()]);
        Log::channel('invoice')->error('Facturación pendiente de revisión', [
            'solicitud_id' => $this->solicitudId, 'version' => $this->version, 'error' => $exception->getMessage(),
        ]);
    }

    private function request(): \Illuminate\Database\Query\Builder
    {
        // Query Builder preserves conditional updates without model events or implicit timestamps.
        return InvoiceRequest::query()->toBase()->where('id', $this->solicitudId)->where('version', $this->version);
    }
}
