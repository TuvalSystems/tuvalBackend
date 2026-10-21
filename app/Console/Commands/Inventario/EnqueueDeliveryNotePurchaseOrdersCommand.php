<?php

namespace App\Console\Commands\Inventario;

use App\Enums\Admin\Sync\StatusSync;
use App\Models\Admin\SyncLog;
use App\Services\Inventario\Bodegas\Contracts\DeliveryNotePurchaseOrderServiceInterface;
use Illuminate\Console\Command;
use Throwable;

class EnqueueDeliveryNotePurchaseOrdersCommand extends Command
{
    protected $signature = 'guias:encolar-ordenes-compra {--limit=200 : Máximo de guías por ejecución}';
    protected $description = 'Encola la generación de órdenes de compra de guías enviadas o recibidas';

    public function handle(DeliveryNotePurchaseOrderServiceInterface $service): int
    {
        try {
            $count = $service->enqueuePendingGuides((int) $this->option('limit'));
            $this->info("Guías seleccionadas para la cola: {$count}");
            return self::SUCCESS;
        } catch (Throwable $exception) {
            SyncLog::logErrorSync('DeliveryNotePurchaseOrder', 'enqueue_purchase_orders', 'enqueuePendingGuides',
                $exception->getMessage(), null, null, ['limit' => $this->option('limit')], 0, StatusSync::FAILED);
            $this->error($exception->getMessage());
            return self::FAILURE;
        }
    }
}
