<?php

namespace App\Services\Venta\Pedidos;

use App\Services\Venta\Pedidos\Contracts\OrderGuideServiceInterface;
use Illuminate\Support\Facades\DB;

class OrderGuideService implements OrderGuideServiceInterface
{
    public function __construct()
    {
        // Inyecta dependencias
    }


    public function getOrdersWithGuidesByPeriod(int $period, ?string $pvCode, ?string $dispacth = null): array
    {

        return DB::select(
            'CALL sp_pedidos_guias_periodo(?, ?, ?)',
            [
                $period,
                $pvCode,
                $dispacth
            ]
        );
    }
}
