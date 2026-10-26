<?php

namespace App\Services\Ventas\Pedidos;

use App\Services\Ventas\Pedidos\Contracts\OrderGuideServiceInterface;
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

    public function fetchListDeliveryNoteSp(int $period, string $warehouse, ?string $numGuide = null): array
    {
        return DB::select(
            'CALL sp_listado_guias_remision(?, ?, ?)',
            [
                $warehouse,
                $period,
                $numGuide
            ]
        );
    }

    public function fetchListdeliveryNoteHeader(int $period, string $warehouse, ?string $numGuide = null): array
    {
        return DB::select(
            'CALL sp_listado_guias_remision_all_documentos_cabecera(?,?,?)',
            [

                $warehouse,
                $period,
                $numGuide
            ]
        );
    }
}
