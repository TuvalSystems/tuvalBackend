<?php

namespace App\Console\Commands\Devoluciones;

use App\Services\Devoluciones\Contracts\DocumentCancellationRetryServiceInterface;
use Illuminate\Console\Command;

class RetryFailedTodayDocumentCancellationsCommand extends Command
{

    protected $signature = 'seedbilling:retry-failed-today-cancellations';

    protected $description = 'Reintenta las anulaciones del día actual que no fueron enviadas correctamente a SeedBilling';


    public function handle(DocumentCancellationRetryServiceInterface $service)
    {
        $this->info('Reintentando anulaciones fallidas del día actual...');
        $service->handleSendCancellationsWithError();
        $this->info('Proceso finalizado.');
        return self::SUCCESS;
    }
}
