<?php

namespace App\Console\Commands\Reservas;

use Carbon\Carbon;
use Illuminate\Console\Command;
use App\Models\reservas\GpReserva;
use Illuminate\Support\Facades\Log;
use App\Notifications\User\UserNotification;

class NotifyExpiringReservations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reservas:notificacion-expiracion';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Notifica reservas que expiran mañana (y liberarán stock).';

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
    public function handle()
    {
        $tz = config('app.timezone', 'America/Guayaquil');
        $tz = 'America/Guayaquil';
        $manana = Carbon::tomorrow($tz)->toDateString();


        $reservas = GpReserva::query()->with('usuarioingreso','cotizacion')->where('tipo_reserva_id', 1)->where('estado', 'aprobado')->where('fe_autorizacion_reserva', $manana);
        $total = 0;

        $reservas->chunkById(100, function ($lote) use (&$total) {
            foreach ($lote as $key => $cot) {
                $creator = $cot->usuarioingreso;
                $quote = $cot->cotizacion;
                $total++;
                $title = "Reserva por expirar";
                $msg = "Tu reserva para la cotizacion {$quote->cotizacion} expira mañana y se liberara el stock.";
                $creator->notify((new UserNotification($title,  $msg)));
            }
        });
        $this->info("Reservas avisadas: {$total}");
        Log::info("reservas:notify-expiring => {$total}");
        return self::SUCCESS;
    }
}
