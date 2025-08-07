<?php

namespace App\Services\SqlServer\Bodega;

use App\Services\SqlServer\Bodega\Contracts\StockSqlServiceInterface;
use App\Traits\JsonResponseTrait;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class StockSqlService implements StockSqlServiceInterface
{
    use JsonResponseTrait;
    public function __construct() {}

    public function consultStockWarehouse(string $article, string $warehouse)
    {
        try {
            $sql = <<<SQL
            EXEC SP_TUV_OBTENER_STOCK_BODEGA
                @ARTICULO = :article,
                @BODEGA =   :warehouse;
            SQL;

            $bindings = [
                'article' => $article,
                'warehouse' => $warehouse,
            ];

          return  DB::connection('sqlsrvtuv')->select($sql, $bindings);
        } catch (Throwable $th) {
            Log::channel('sql_errors')->error('Error en consultaStockWarehouse (SP_TUV_OBTENER_STOCK_BODEGA):', [
                'error' => $th->getMessage(),
                'trace' => $th->getTraceAsString()
            ]);

            return $this;
        }
    }
}
