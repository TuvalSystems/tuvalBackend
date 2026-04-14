<?php

namespace App\Console\Commands\Pedidos;

use App\Services\Venta\OrderJobService;
use App\Supports\DbUtils;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Console\Command\Command as Cmd;

class InvoiceReservedOrders extends Command
{
    protected $signature = 'pedidos:facturar-reservas';
    protected $description = 'Comando generado para facturar pedidos con origen reserva';

    public function handle(OrderJobService $orderJobService)
    {
        $this->info('🔄 Iniciando facturación de pedidos con origen "reserva"...');
        $t0 = microtime(true);

        try {
            // Si tu servicio devuelve métricas ['processed','invoiced','skipped','failed'], se usarán abajo.
            $result = $orderJobService->generateInvoiceForReservedOrders();

            $duration = round(microtime(true) - $t0, 2);
            $payload = [
                'task'           => 'generateInvoiceForReservedOrders',
                'duration_sec'   => $duration,
                'ended_at'       => now()->toDateTimeString(),
                'peak_memory_mb' => round(memory_get_peak_usage(true) / 1048576, 1),
            ];

            if (is_array($result)) {
                $payload = array_merge($payload, [
                    'processed' => $result['processed'] ?? null,
                    'invoiced'  => $result['invoiced'] ?? null,
                    'skipped'   => $result['skipped'] ?? null,
                    'failed'    => $result['failed'] ?? null,
                ]);

                $this->info(sprintf(
                    "✅ Completado en %ss. Facturadas: %d | Omitidas: %d | Fallidas: %d | Procesadas: %d",
                    $duration,
                    (int)($result['invoiced'] ?? 0),
                    (int)($result['skipped'] ?? 0),
                    (int)($result['failed'] ?? 0),
                    (int)($result['processed'] ?? 0),
                ));
            } else {
                $this->info("✅ Proceso completado en {$duration}s.");
            }

            Log::channel('cron_process')->info('orders:invoice-reservations finished', $payload);
            return Cmd::SUCCESS;
        } catch (\Throwable $e) {
            Log::channel('cron_process')->error('orders:invoice-reservations error', [
                'exception' => get_class($e),
                'message'   => $e->getMessage(),
                'code'      => $e->getCode(),
                'trace'     => $e->getTraceAsString(),
            ]);
            $this->error('❌ Error al facturar reservas. Revisa logs.');
            return Cmd::FAILURE;
        } finally {
            // vacía el pool de conexiones para evitar problemas en siguientes jobs
           DbUtils::flush();
        }
    }
}
