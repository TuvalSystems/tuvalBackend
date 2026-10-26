<?php

namespace App\Console\Commands\Cotizaciones;

use App\Jobs\Venta\Cotizaciones\NotifyUnsoldQuotesJob;
use App\Mail\Cotizacion\RecordatorioCotizacionNoVentaMail;
use App\Models\Admin\PuntosVenta\GpPuntoVenta;
use App\Services\Venta\Contracts\QuotationServiceInterface;
use Carbon\Carbon;
use Illuminate\Console\Command;
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
    public function handle(QuotationServiceInterface $quotationService)
    {
        $hoy = now()->startOfDay();
        Log::info('✅ [CotizacionesNoVendidas] - Command executed at ' . now());
        $this->info('🔄 Realizando escaneo de Cotizaciones para notificar ...');

        $startTime = Carbon::now();

        $cotizacionesSearch = $quotationService->getQuotationsWithReminderToday();

        // Cotizaciones sin fecha, solo el 1er día del mes

        $total = $cotizacionesSearch->count();
        $this->info("🔎 Encontradas {$total} cotizaciones con recordatorio para hoy.");


        if ($total === 0) {
            $this->info('✅ No hay cotizaciones para notificar el dia de hoy.');
            return 0;
        }

        $this->info("🔎 Total {$total} cotizaciones para notificar.");
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        try {

            $ptoVentasEmail = GpPuntoVenta::where('PTO_VTA_ESTADO', 'ACTIVO')->select('id', 'PTO_VTA_ID', 'PTO_VTA_DESCRIPCION', 'email')->get()->keyBy('PTO_VTA_ID');
            $cotizacionesAgrupadas = collect($cotizacionesSearch)->groupBy('PTO_VTA_ID');
            $resultado = [];
            foreach ($cotizacionesAgrupadas as $ptoVentaId => $cotizacion) {
                if (isset($ptoVentasEmail[$ptoVentaId])) {
                    $resultado[] = [
                        'ptoVentaId' => $ptoVentaId,
                        'ptoVenta' => $ptoVentasEmail[$ptoVentaId]['PTO_VTA_DESCRIPCION'],
                        'email' => $ptoVentasEmail[$ptoVentaId]['email'],
                        'cotizaciones' => $cotizacion
                    ];
                }
            }

            foreach ($resultado as $value) {
                $ptoVentaId = $value['ptoVentaId'];
                $PtoVentaDescripcion = $value['ptoVenta'];
                $email = $value['email'];
                // $email = 'sistemas6@tuvalsa.com';
                // $userId = 179;
                $cotizaciones = data_get($value['cotizaciones'], []);
                Mail::to($email)->send(new RecordatorioCotizacionNoVentaMail($cotizaciones, $PtoVentaDescripcion));
                foreach ($cotizaciones as $cot) {
                    $pvId = GpPuntoVenta::where('PTO_VTA_ID', $cot->PTO_VTA_ID)->value('id');
                    $dateRegister = Carbon::parse($cot->fecha_registro_recordatorio)->format('Y-m-d');
                    $title = 'Cotización No Vendida';
                    $msg = 'La cotización ' . $cot->cotizacion . ' de la ' . $cot->PTO_VTA_DESCRIPCION . ' no ha sido vendida y tiene un recordatorio ingresado el '
                        . $dateRegister . ' programado para hoy.';
                        $data = [
                            'id'            => $cot->id,
                            'cotizacion'    => $cot->cotizacion,
                            'pvId'          => $pvId,
                            'pvCodigo'      => $cot->PTO_VTA_ID,
                            'estado'        => $cot->estado
                        ];
                    NotifyUnsoldQuotesJob::dispatch($title, $msg, $data, $cot->user_id);
                }
            }
            $bar->advance();
        } catch (\Throwable $th) {
            Log::error("❌ Error al enviar correo las cotizaciones {$cotizacionesSearch->pluck('id')->toArray()}: {$th->getMessage()}");
        }
        $bar->finish();
        return 0;
    }
}
