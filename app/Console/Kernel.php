<?php

namespace App\Console;

use App\Jobs\Devoluciones\EnqueueDevoluciones;
use App\Models\borradores\CotizacionBorrador;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Queue\Middleware\WithoutOverlapping;

class Kernel extends ConsoleKernel
{

    protected function schedule(Schedule $schedule)
    {
        $schedule->job(new EnqueueDevoluciones(10))
            ->timezone('America/Guayaquil')
            ->everyMinute()
            ->withoutOverlapping(5)
            ->runInBackground();


        //se ejecuta una vez al dia a las 08:00
        $schedule->command('cotizaciones:notificar-noventa')
            ->dailyAt('08:00')

            ->runInBackground();

        //se ejecuta el 1 de cada mez
        $schedule->command('pedidos:enviar-facturas-mes-siguiente')
            ->monthlyOn(1, '02:00')
            ->when(function () {
                return now()->day <= 7;
            })
            ->timezone('America/Guayaquil')->withoutOverlapping(10)
            ->runInBackground(); // hace que no bloquee otros jobs

        // ejecuta cada 2 horas la actualización de articulos
        $schedule->command('articulol:update')
            ->dailyAt('03:15')
            ->timezone('America/Guayaquil')
            ->withoutOverlapping(60)
            ->runInBackground();

        $schedule->command('pedidos:facturar-reservas')
            ->timezone('America/Guayaquil')
            ->between('06:00', '22:00')
            ->everyTenMinutes()              // frecuencia
            ->withoutOverlapping(20)            // evita procesos en paralelo
            ->runInBackground()
        ; // hace que no bloquee otros jobs

        $schedule->command('reservas:notificacion-expiracion')
            ->timezone('America/Guayaquil')
            ->dailyAt('07:30')
            ->withoutOverlapping(30)
            ->runInBackground();

        $schedule->command('reservas:release-stock')
            ->timezone('America/Guayaquil')
            ->dailyAt('07:30')
            ->withoutOverlapping(30)
            ->runInBackground();

        $schedule->command('model:prune', [
            '--model' => [CotizacionBorrador::class]
        ])
            ->timezone('America/Guayaquil')
            ->dailyAt('02:00');


        //limpieza de documentos
        $schedule->command('media:clean-old-pdfs --months=3')
            ->dailyAt('02:00')
            ->WithoutOverlapping(30);


        $schedule->command('telescope:prune')->daily();

        //facturar pedidos en estado enviando
        $schedule->command('pedidos:facturar-enviando')
            ->timezone('America/Guayaquil')
            ->everyTwoHours()
            ->withoutOverlapping(20)
            ->runInBackground();
    }


    protected function commands()
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
