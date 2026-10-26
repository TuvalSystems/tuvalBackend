<?php

namespace App\Console\Commands\Pedidos;

use App\Services\Ventas\Pedidos\OrderJobService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;


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
        Log::channel('cron_process')->info('✅ [SendInvoiceNextMonthJob] - Command executed at ' . now());
        $this->info('🔄 Enviando Pedidos a Facturar...');

        $startTime = Carbon::now();
        try {
            $orderJobService->sendInvoiceNextMonthCron();
            $endTime = Carbon::now();
            $duration = $startTime->diffInSeconds($endTime);
            Log::channel('cron_process')->info("✅ [SendInvoiceNextMonthJob] - Command finished at {$endTime} (Duration: {$duration} seconds)");
            $this->info('✅ Proceso completado exitosamente.');
        } catch (\Exception $e) {
            Log::channel('cron_process')->error("[SendInvoiceNextMonthJob] - Error: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            $this->error('❌ Error al enviar pedidos a facturar. Ver logs para más detalles.');
        }
        return 0;
    }
}
