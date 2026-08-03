<?php

namespace App\Console\Commands\Pedidos;

use App\Services\Venta\Contracts\OrderServiceInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendInvoicesToGpCommand extends Command
{
    protected $signature = 'pedidos:enviar-facturas-gp';

    protected $description = 'Procesa el envio de facturas pendientes a GP';

    public function handle(OrderServiceInterface $orderService): int
    {
        Log::channel('cron_process')->info('[SendInvoicesToGpCommand] - Command executed at ' . now());

        try {
            $orderService->sendInvoicesToGp();
            Log::channel('cron_process')->info('[SendInvoicesToGpCommand] - Command finished at ' . now());

            return self::SUCCESS;
        } catch (\Throwable $e) {
            Log::channel('cron_process')->error('[SendInvoicesToGpCommand] - Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            $this->error('Error al enviar facturas a GP. Ver logs para mas detalles.');

            return self::FAILURE;
        }
    }
}
