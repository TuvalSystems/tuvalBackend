<?php

namespace App\Console;

use App\Jobs\Devoluciones\EnqueueDevoluciones;
use App\Models\Admin\SyncLog;
use App\Models\borradores\CotizacionBorrador;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{

    private const MAINTENANCE_TIME = '02:00';
    private const TIMEZONE = 'America/Guayaquil';

    protected function schedule(Schedule $schedule)
    {

        $this->scheduleOrdersAndQuotes($schedule);
        $this->scheduleStockAndArticles($schedule);
        $this->scheduleSynchronizations($schedule);
        $this->scheduleCleanUpTasks($schedule);
        $this->scheduleDevolutions($schedule);
    }



    /**
     * Tareas relacionadas con Pedidos, Cotizaciones y Devoluciones
     */

    private function scheduleOrdersAndQuotes(Schedule $schedule)
    {
        $schedule->command('cotizaciones:notificar-noventa')
            ->timezone(self::TIMEZONE)
            ->dailyAt('08:30')
            ->runInBackground();

        $schedule->command('pedidos:enviar-facturas-mes-siguiente')
            ->monthlyOn(1, self::MAINTENANCE_TIME)
            ->when(fn() => now()->day <= 7)
            ->timezone(self::TIMEZONE)
            ->withoutOverlapping(10)
            ->runInBackground();

        $schedule->command('pedidos:facturar-reservas')
            ->timezone(self::TIMEZONE)
            ->between('06:00', '22:00')
            ->everyTenMinutes()
            ->withoutOverlapping(20)
            ->runInBackground();

        $schedule->command('pedidos:facturar-enviando')
            ->timezone(self::TIMEZONE)
            ->everyTwoHours()
            ->withoutOverlapping(20)
            ->runInBackground();
    }


    private function scheduleStockAndArticles(Schedule $schedule)
    {
        $schedule->command('articulol:update')
            ->dailyAt('03:15')
            ->timezone(self::TIMEZONE)
            ->withoutOverlapping(60)
            ->runInBackground();

        $schedule->command('reservas:notificacion-expiracion')
            ->timezone(self::TIMEZONE)
            ->dailyAt('07:30')
            ->withoutOverlapping(30)
            ->runInBackground();

        $schedule->command('reservas:release-stock')
            ->timezone(self::TIMEZONE)
            ->dailyAt('07:30')
            ->withoutOverlapping(30)
            ->runInBackground();
    }


    private function scheduleSynchronizations(Schedule $schedule)
    {
        $syncCommands = [
            'sync:clients',
            'sync:client-branches',
            'sync:clients-coordinates'
        ];

        foreach ($syncCommands as $command) {
            $schedule->command($command)
                ->timezone(self::TIMEZONE)
                ->everyMinute()
                ->withoutOverlapping(20)
                ->runInBackground();
        }
    }

    private function scheduleCleanUpTasks(Schedule $schedule)
    {
        // Limpieza de modelos específicos
        $schedule->command('model:prune', ['--model' => [CotizacionBorrador::class]])
            ->timezone(self::TIMEZONE)
            ->dailyAt(self::MAINTENANCE_TIME);

        $schedule->command('model:prune', ['--model' => [SyncLog::class]])
            ->timezone(self::TIMEZONE)
            ->dailyAt(self::MAINTENANCE_TIME)
            ->withoutOverlapping(30)
            ->runInBackground();

        // Limpieza de archivos y colas
        $schedule->command('media:clean-old-pdfs --months=3')
            ->timezone(self::TIMEZONE)
            ->dailyAt(self::MAINTENANCE_TIME)
            ->withoutOverlapping(30);

        $schedule->command('queue:prune-failed --hours=72')->daily();
    }

    private function scheduleDevolutions(Schedule $schedule)
    {
        $schedule->job(new EnqueueDevoluciones(10))
            ->timezone(self::TIMEZONE)
            ->everyMinute()
            ->withoutOverlapping(5)
            ->runInBackground();

        $schedule->command('seedbilling:retry-pending-cancelations')
            ->weekdays()
            ->dailyAt('07:00')
            ->timezone(self::TIMEZONE)
            ->withoutOverlapping(60)
            ->runInBackground();
    }


    protected function commands()
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
