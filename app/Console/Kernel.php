<?php

namespace App\Console;

use App\Supports\DbUtils;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{

    protected function schedule(Schedule $schedule)
    {

        $schedule->command('devoluciones:procesar')
            ->everyMinute()
            ->withoutOverlapping(60);

        //se ejecuta una vez al dia a las 08:00
        $schedule->command('cotizaciones:notificar-noventa')
            ->dailyAt('08:00')
            ->after(fn() => DbUtils::flush())
            ->onFailure(fn() => DbUtils::flush());

        //se ejecuta el 1 de cada mez
        $schedule->command('pedidos:enviar-facturas-mes-siguiente')
            ->monthlyOn(9, '02:00')
            ->timezone('America/Guayaquil')->withoutOverlapping(60)
            ->runInBackground()
            ->after(fn() => DbUtils::flush())
            ->onFailure(fn() => DbUtils::flush()); // hace que no bloquee otros jobs

        // ejecuta cada 2 horas la actualización de articulos
        $schedule->command('articulol:update')
            ->dailyAt('03:15')
            ->timezone('America/Guayaquil')
            ->withoutOverlapping(60)
            ->after(fn() => DbUtils::flush())
            ->onFailure(fn() => DbUtils::flush());

        $schedule->command('optimize:clear')
            ->dailyAt('03:15')
            ->timezone('America/Guayaquil')
            ->withoutOverlapping(60)
            ->after(fn() => DbUtils::flush())
            ->onFailure(fn() => DbUtils::flush());
    }


    protected function commands()
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
