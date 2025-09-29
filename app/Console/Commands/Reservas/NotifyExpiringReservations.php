<?php

namespace App\Console\Commands\Reservas;

use App\Models\GpCotizacion;
use App\Notifications\Venta\Reservas\ReservaPorExpirarNotification;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

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

        $reservas = GpCotizacion::query()->with('usuariosolicitudreserva')->where('st_solResInv', 'aprobado')->where('co_tipSolResInv', 1)
            ->where('fe_autSolResInv', $manana);
        $total = 0;

        $reservas->chunkById(100, function ($lote) use (&$total) {
            foreach ($lote as $key => $cot) {
                $creator = $cot->usuariosolicitudreserva;
                $total++;
                $creator->notify((new ReservaPorExpirarNotification('Reserva por expirar', $cot->cotizacion))->onQueue('notifications'));
            }
        });
        $this->info("Reservas avisadas: {$total}");
        Log::info("reservas:notify-expiring => {$total}");
        return self::SUCCESS;
    }
}
