<?php

namespace App\Console\Commands\Pedidos;

use App\Services\Pedidos\OrderJobService;
use App\Supports\DbUtils;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Console\Command\Command as Cmd;


class SendInvoiceNextMonth extends Command
{
    protected $orderJobService;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pedidos:enviar-facturas-mes-siguiente';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Commando para enviar la factura del mes siguiente a facturar';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(OrderJobService $orderJobService)
    {
        $this->info('🔄 Enviando pedidos a facturar (ciclo “siguiente mes”)…');
        $t0 = microtime(true);

        try {
            // Debe devolver métricas: ['processed','invoiced','skipped','failed']
            $result = $orderJobService->sendInvoiceNextMonthCron();

            $duration = round(microtime(true) - $t0, 2);
            $payload = [
                'task'           => 'send-invoice-next-month',
                'duration_sec'   => $duration,
                'ended_at'       => now()->toDateTimeString(),
                'peak_memory_mb' => round(memory_get_peak_usage(true) / 1048576, 1),
            ] + (is_array($result) ? $result : []);

            Log::channel('cron_process')->info('pedidos:enviar-facturas-mes-siguiente finished', $payload);

            $this->info(sprintf(
                "✅ Fin en %ss | Procesadas: %d | Facturadas: %d | Omitidas: %d | Fallidas: %d",
                $duration,
                (int)($result['processed'] ?? 0),
                (int)($result['invoiced']  ?? 0),
                (int)($result['skipped']   ?? 0),
                (int)($result['failed']    ?? 0),
            ));

            // Si hubo fallidas, devuelve FAILURE para que el scheduler/monitor lo note
            return (($result['failed'] ?? 0) > 0) ? Cmd::FAILURE : Cmd::SUCCESS;
        } catch (\Throwable $e) {
            Log::channel('cron_process')->error('pedidos:enviar-facturas-mes-siguiente error', [
                'exception' => get_class($e),
                'message'   => $e->getMessage(),
                'code'      => $e->getCode(),
                'trace'     => $e->getTraceAsString(),
            ]);
            $this->error('❌ Error al ejecutar la facturación de siguiente mes. Revisa logs.');
            return Cmd::FAILURE;
        } finally {
            // vacía el pool de conexiones para evitar problemas en siguientes jobs
            DbUtils::flush();
        }
    }
}
