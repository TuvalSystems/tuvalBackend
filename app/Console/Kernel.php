<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{

    protected function schedule(Schedule $schedule)
    {
        //se ejecuta todos los dias cada 1 minuto de lunesa viernes de 6:00 a 20:00 sin sobreponer la ejecucion
        // $schedule->command('devoluciones:procesar')->everyMinute()->between('4:00', '20:00')->withoutOverlapping(60);

        $schedule->command('devoluciones:procesar')->everyMinute()->withoutOverlapping(60);

        //se ejecuta una vez al dia a las 08:00
        $schedule->command('cotizaciones:notificar-noventa')->dailyAt('08:00');
    }


    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
