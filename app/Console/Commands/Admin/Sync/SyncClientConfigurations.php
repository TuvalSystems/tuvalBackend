<?php

namespace App\Console\Commands\Admin\Sync;

use App\Jobs\Admin\SyncClientConfigurationsJob;
use Illuminate\Console\Command;
use Throwable;

class SyncClientConfigurations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:client-configurations
                            {--batch-size=1000 : Registros por lote, entre 1 y 1000}
                            {--max-batches= : Limite opcional de lotes para pruebas}';

    protected $description = 'Ejecuta la sincronización de configuración de sucursales de clientes desde GP';


    public function handle(): int
    {
        $batchSize = filter_var($this->option('batch-size'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 1000],
        ]);
        $maxBatchesOption = $this->option('max-batches');
        $maxBatches = $maxBatchesOption === null ? null : filter_var($maxBatchesOption, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);

        if ($batchSize === false || $maxBatches === false) {
            $this->error('Usa --batch-size entre 1 y 1000 y --max-batches como entero positivo.');

            return self::INVALID;
        }

        try {
            SyncClientConfigurationsJob::dispatchSync($batchSize, $maxBatches);
        } catch (Throwable $exception) {
            report($exception);
            $this->error('No se pudo ejecutar la sincronización de configuración de clientes. Consulta los logs para más detalles.');

            return self::FAILURE;
        }

        $this->info('Proceso de sincronización de configuración de clientes finalizado correctamente.');

        return self::SUCCESS;
    }
}
