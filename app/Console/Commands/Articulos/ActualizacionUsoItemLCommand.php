<?php

namespace App\Console\Commands\Articulos;

use App\Models\GpArticulo;
use App\Services\Reposiciones\ItemsLService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ActualizacionUsoItemLCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'articulol:update';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Comando para actualizar la disponibilidad de los articulos';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(ItemsLService $itemsLService)
    {
        $articulosPorClase = $itemsLService->getListArticleL();
        $diasMaxPorClase   = $itemsLService->getDataItemLMaxDay();
        foreach ($articulosPorClase as  $articulo) {
            $cooldownDays = optional($diasMaxPorClase->get($articulo->ART_CLASE_ARTICULO))->DATOS_ITEMS_L_DIA_MAXIMO ?? 60;

            $info = $itemsLService->availabilityForItem($articulo->ART_CODIGO, $cooldownDays);

            if ($itemsLService->isTemporalCart($articulo->ART_CODIGO)) {
                $info['disponible'] = false;
            }

            $updateItem = array_merge($info, [
                'disponible' => $info['disponible'] ? 1 : 0,
            ]);
            Log::info('Actualizando articulo ' . $articulo->ART_CODIGO . ' con :', [
                'updateItem' => $updateItem
            ]);
            GpArticulo::where('ART_CODIGO', $articulo->ART_CODIGO)->update($updateItem);
        }
        return 0;
    }
}
