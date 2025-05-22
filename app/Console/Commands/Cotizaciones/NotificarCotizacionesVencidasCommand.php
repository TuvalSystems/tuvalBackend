<?php

namespace App\Console\Commands\Cotizaciones;

use App\Mail\Cotizacion\RecordatorioCotizacionNoVentaMail;
use App\Notifications\RecordatorioCotizacionNotification;
use App\Services\Notificacion\Contracts\NotificationServiceInterface;
use App\Services\Venta\Contracts\QuotationServiceInterface;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class NotificarCotizacionesVencidasCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cotizaciones:notificar-noventa';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Comando para notificar cotizaciones no vendidas';

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
    public function handle(QuotationServiceInterface $quotationService, NotificationServiceInterface $notificationService)
    {
        $hoy = now()->startOfDay();
        Log::info('✅ [CotizacionesNoVendidas] - Command executed at ' . now());
        $this->info('🔄 Realizando escaneo de Cotizaciones para notificar ...');

        $startTime = Carbon::now();

        $cotizaciones = $quotationService->getQuotationsWithReminderToday();
        // Cotizaciones sin fecha, solo el 1er día del mes

        $total = $cotizaciones->count();
        $this->info("🔎 Encontradas {$total} cotizaciones con recordatorio para hoy.");


        if ($total === 0) {
            $this->info('✅ No hay cotizaciones para notificar el dia de hoy.');
            return 0;
        }

        $this->info("🔎 Total {$total} cotizaciones para notificar.");
        $bar = $this->output->createProgressBar($total);
        $bar->start();


        try {
            Mail::to('sistemas6@tuvalsa.com')->send(new RecordatorioCotizacionNoVentaMail($cotizaciones));
            foreach ($cotizaciones as $cot) {
                $notificationService->sendNotification(
                    $cot->user_id,
                    'Cotización No Vendida',
                    'La cotización ' . $cot->cotizacion . ' no ha sido vendida y tiene un recordatorio programado para hoy.',

                );
            }
            $bar->advance();
        } catch (\Throwable $th) {
            Log::error("❌ Error al enviar correo las cotizaciones {$cotizaciones->pluck('id')->toArray()}: {$th->getMessage()}");
        }
        $bar->finish();
        return 0;
    }
}
