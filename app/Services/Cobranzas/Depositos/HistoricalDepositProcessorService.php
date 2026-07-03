<?php

namespace App\Services\Cobranzas\Depositos;

use App\Models\Cobranzas\GpCobrosDeposito;
use App\Services\Cobranzas\Depositos\Contracts\HistoricalDepositProcessorServiceInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class HistoricalDepositProcessorService implements HistoricalDepositProcessorServiceInterface
{
    public function __construct()
    {
        // Inyecta dependencias
    }


    public function fetchListHistoricalDeposits(array $filters) :LengthAwarePaginator
    {

        $search     = data_get($filters, 'search', '');
        $perpage    = data_get($filters, 'perpage', 10);
        $sortField  = data_get($filters, 'sortField', 'created_at');
        $sortOrder  = strtolower(data_get($filters, 'sortOrder', 'desc')) === 'asc' ? 'asc' : 'desc';

        $allowedSorts = [
            'id'                    => 'gcd.id',
            'agencia'               => 'gpv.PTO_VTA_DESCRIPCION',
            'usuario_ingreso'       => 'us2.USERID',
            'ruc'                   => 'gcli.CLI_RUC',
            'razon_social'          => 'gcli.CLI_RAZON_SOCIAL',
            'banco'                 => 'gcb.CB_DESCRIPCION',
            'fecha_creacion_cobro'  => 'gc.fecha'
        ];
        $orderColumn = data_get($allowedSorts, $sortField . 'gdc.created_at');

        $query = GpCobrosDeposito::select([
            'gcd.id',
            'gpv.PTO_VTA_DESCRIPCION as agencia',
            'gc.numero_recibo',
            'gc.fecha as fecha_creacion_cobro',
            'us2.USERID as usuario_ingreso',
            'gcli.CLI_RUC as ruc',
            'gcli.CLI_RAZON_SOCIAL as razon_social',
            'gcb.CB_DESCRIPCION as banco',
            'gcd.tipo',
            'gcd.comprobante',
            'gcd.monto',
            'gcd.estado',
            'gcd.fecha_aprobacion_rechazo as fecha_registro',
            'us.USERID as usuario_registro'
        ])
            ->from('gp_cobros_depositos as gcd')
            ->leftJoin('gp_usuarios as us', 'gcd.user_aprobador_rechazo_id', '=', 'us.id')
            ->join('gp_cobros as gc', 'gcd.cobro_id', '=', 'gc.id')
            ->join('gp_usuarios as us2 ', 'gc.user_id', '=', 'us2.id')
            ->join('gp_clientes as gcli', 'gc.cliente_id', '=', 'gcli.id')
            ->leftJoin('gp_punto_ventas as gpv', 'gpv.id', '=', 'gc.pv_id')
            ->join('gp_caja_bancos as gcb', 'gcd.banco_id', '=', 'gcb.id');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('us.USERID', 'like', "%{$search}%")
                    ->orWhere('gc.numero_recibo', 'like', "%{$search}%")
                    ->orWhere('gcli.CLI_RUC', 'like', "%{$search}%")
                    ->orWhere('gcli.CLI_RAZON_SOCIAL', 'like', "%{$search}%")
                    ->orWhere('gpv.PTO_VTA_DESCRIPCION', 'like', "%{$search}%");
            });
        }

        return $query
        ->orderBy($orderColumn, $sortOrder)
        ->paginate($perpage);

    }
}
