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

    public function consultStockWarehouseSql(array $params)
    {
        try {
            $sql = <<<'SQL'
            EXEC SP_TUV_OBTENER_STOCK_BODEGA
                @ARTICULO = :article,
                @BODEGA   = :warehouse
            SQL;

            $bindings = [
                'article' => $params['articulo'],
                'warehouse' => strtoupper($params['bodega']),
            ];
            $data =  DB::connection('sqlsrvtuv')->select($sql, $bindings);
            if (empty($data)) {
                throw new \Exception('No se encontraron registros', 404);
            }
            $formattedData = $this->formatResultStocks($data);
            Log::channel('sql_errors')->info('Stock obtenido (consultStockWarehouseSql)', $formattedData);
            return $formattedData;
        } catch (Throwable $th) {
            Log::channel('sql_errors')->error('Error en consultaStockWarehouse (SP_TUV_OBTENER_STOCK_BODEGA):', [
                'error' => $th->getMessage(),
                'trace' => $th->getTraceAsString()
            ]);
            throw new \Exception('Error interno al consultar stock (consultStockWarehouseSql) ', 500);
        }
    }

    public function formatResultStocks($rows)
    {
        $item = reset($rows);
        return [
            'DESCRIPCION_ARTICULO'      => $item->DESCRIPCION_ARTICULO,
            'STO_ART_ARTICULO'          => trim($item->ARTICULO),
            'STO_ART_BODEGA'            => trim($item->BODEGA),
            // 'STOCK_MANO'                => $item->STOCK_MANO,
            // 'STOCK_RESERVADO'           => $item->STOCK_RESERVADO,
            'STO_ART_STOCK_DISPONIBLE'  => number_format((float)$item->STOCK_DISPONIBLE, 2, '.', ''),
            // 'STOCK_USO'                 => $item->STOCK_USO,
            // 'STOCK_DANADO'              => $item->STOCK_DANADO,
        ];
    }



    /**
     * @param array $params
     * @return array
     *  params contiene un array de articulos con bodegas diferentes
     */

    public function getStockByArticleBatch(array $params)
    {

        if ($params === []) return [];

        // Deduplicar manteniendo orden
        $unique = [];
        $order  = [];
        foreach ($params as $p) {
            $key = trim($p['articulo']) . '|' . strtoupper(trim($p['bodega']));
            if (!isset($unique[$key])) {
                $unique[$key] = ['articulo' => trim($p['articulo']), 'bodega' => strtoupper(trim($p['bodega']))];
            }
            $order[] = $key;
        }

        // Consultar cada par único
        $byKey = [];
        foreach ($unique as $key => $p) {
            $byKey[$key] = $this->consultStockWarehouseSql($p); // puede devolver array o null
        }

        // Reconstruir en el mismo orden del input
        $out = [];
        foreach ($order as $key) {
            $out[] = $byKey[$key] ?? null;
        }

        return $out;
    }
}
