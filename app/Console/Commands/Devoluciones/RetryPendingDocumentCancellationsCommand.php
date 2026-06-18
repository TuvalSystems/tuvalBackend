<?php

namespace App\Console\Commands\Devoluciones;


use App\Services\Devoluciones\Contracts\DocumentCancellationRetryServiceInterface;

use Illuminate\Console\Command;

class RetryPendingDocumentCancellationsCommand extends Command
{
    protected $signature = 'seedbilling:retry-pending-cancelations';
    protected $description = 'Verificar documentos de anulaciones de seedbilling y reintenar enviarlas si se necesita';
    public function handle(DocumentCancellationRetryServiceInterface $service)
    {
        $this->info('Iniciando verificación de anulaciones pendientes...');
        $service->handleCancellationRetries();
        $this->info('Proceso finalizado.');

        return self::SUCCESS;
    }
}
