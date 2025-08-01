<?php

namespace App\Services\SqlServer\Ventas;

use App\Services\SqlServer\Ventas\Contracts\ClientServiceInterface;
use Illuminate\Support\Facades\DB;

class ClientService implements ClientServiceInterface
{

    public function __construct()
    {
    }


    /**
     *  Valida si el cliente tiene un segmento asignado en la base de datos
     * @param mixed $ruc
     * @return object|\Illuminate\Database\Eloquent\Model|\Illuminate\Database\Query\Builder|null
     */
    public function validateClientforRucSegment($ruc){
        return DB::connection('sqlsrvdyn')
        ->table('vw_clientesPorSegmento')
        ->where('tx_cli', $ruc)
        ->first();
    }
}
