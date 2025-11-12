<?php

namespace App\Console;

use App\Jobs\Devoluciones\EnqueueDevoluciones;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{

    protected function schedule(Schedule $schedule)
    {
        $schedule->job(new EnqueueDevoluciones(200))
            ->onConnection('redis')
            ->onQueue('devoluciones')
            ->everyMinute()
            ->withoutOverlapping(60)
            ->runInBackground();
        // $schedule->command('devoluciones:procesar')
        //     ->everyMinute()
        //     ->withoutOverlapping(60)
        //     ->runInBackground();

        //se ejecuta una vez al dia a las 08:00
        $schedule->command('cotizaciones:notificar-noventa')
            ->dailyAt('08:00')

            ->runInBackground();

        //se ejecuta el 1 de cada mez
        $schedule->command('pedidos:enviar-facturas-mes-siguiente')
            ->monthlyOn(9, '02:00')
            ->timezone('America/Guayaquil')->withoutOverlapping(60)
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
            ->withoutOverlapping(30)            // evita procesos en paralelo
            ->runInBackground()
        ; // hace que no bloquee otros jobs

        $schedule->command('reservas:notificacion-expiracion')
            ->timezone('America/Guayaquil')
            ->dailyAt('09:00')
            ->withoutOverlapping(30)
            ->runInBackground();

        $schedule->command('reservas:release-stock')
            ->timezone('America/Guayaquil')
            ->dailyAt('09:00')
            ->withoutOverlapping(30)
            ->runInBackground();
    }


    protected function commands()
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
