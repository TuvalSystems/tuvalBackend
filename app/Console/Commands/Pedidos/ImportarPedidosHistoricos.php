<?php

namespace App\Console\Commands\Pedidos;

use App\Services\Imports\Contracts\OrdersImportInterface;
use Illuminate\Console\Command;

class ImportarPedidosHistoricos extends Command
{
    // El nombre para ejecutarlo en terminal
    protected $signature = 'import:pedidos-historicos {fechaInicio} {fechaFin}';

    protected $description = 'Importa y actualiza pedidos de forma masiva por rango de fechas';

    public function handle(OrdersImportInterface $importer)
    {
        $inicio = $this->argument('fechaInicio');
        $fin = $this->argument('fechaFin');

        $this->info("Iniciando importación desde $inicio hasta $fin...");

        $inicioTiempo = microtime(true);

        // Llamamos a tu servicio refactorizado
        $resultado = $importer->updateDataSqlServerOrdersAndDetail($inicio, $fin);

        $duracion = round(microtime(true) - $inicioTiempo, 2);

        $this->info("¡Proceso Terminado en {$duracion} segundos!");
        $this->table(
            ['Métrica', 'Cantidad'],
            [
                ['Cabeceras Procesadas', $resultado['total_cabeceras_procesadas']],
                ['Detalles Procesados', $resultado['total_detalles_procesados']],
            ]
        );

        return 0;
    }
}
