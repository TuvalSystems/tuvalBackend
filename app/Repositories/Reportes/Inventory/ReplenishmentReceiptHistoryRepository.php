<?php

declare(strict_types=1);

namespace App\Repositories\Reportes\Inventory;

use App\Repositories\Reportes\Inventory\Contracts\ReplenishmentReceiptHistoryRepositoryInterface;
use Illuminate\Support\Facades\DB;

class ReplenishmentReceiptHistoryRepository implements ReplenishmentReceiptHistoryRepositoryInterface
{
    public function getHistory(string $period, ?string $replenishmentNumber = null): array
    {
        return DB::connection('mysql')->select(
            'CALL tuvalapi.sp_historial_reposiciones_guia_ingreso(?, ?)',
            [$replenishmentNumber, $period],
        );
    }
}
