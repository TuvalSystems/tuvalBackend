<?php

namespace App\Console\Commands\Pedidos;

use App\Services\Inventario\Despachos\Contracts\DispatchServiceInterface;
use Illuminate\Console\Command;

class checkCompletedDispatches extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'despachos:check-completed';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verifica si los despachos completados tienen la información de la guía de remisión y la clave de acceso asignada.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(DispatchServiceInterface $service)
    {
        $service->checkCompletedDispatch();
        return 0;
    }
}
