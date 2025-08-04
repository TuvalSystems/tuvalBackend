<?php

namespace App\Repositories\SqlServer\Venta;

use App\Repositories\SqlServer\Venta\Contracts\ClientSqlRepositoryInterface;
use Illuminate\Support\Facades\DB;

class ClientSqlRepository implements ClientSqlRepositoryInterface
{

    public function getCheckoutClientSegment($ruc)
    {
        return DB::connection('sqlsrvdyn')
            ->table('vw_clientesPorSegmento')
            ->where('tx_cli', $ruc)
            ->first();
    }

    public function getQueryListSegments()
    {
        return DB::connection('sqlsrvdyn')
            ->table('vw_segmentosClientes')
            ->orderBy('co_atrPadre', 'desc')
            ->get();
    }

    public function getQueryListSubSegmentsBySegment($segmento)
    {
        return DB::connection('sqlsrvdyn')->table('vw_segmentosClientes')
        ->where('co_atrPadre', '=',  $segmento)
        ->orderBy('co_atrHijo', 'desc')->get();
    }
}
