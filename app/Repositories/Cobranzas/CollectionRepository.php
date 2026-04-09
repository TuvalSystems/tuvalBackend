<?php

namespace App\Repositories\Cobranzas;

use App\Models\Cobranzas\GpCobrosCheques;
use App\Models\Cobranzas\GpCobrosDeposito;
use App\Models\Cobranzas\GpCobrosEfectivoMonto;
use App\Models\Cobranzas\GpCobrosRetenciones;
use App\Models\GpCobrosAplicacionMonto;
use App\Models\GpCobrosTarjetaMonto;
use App\Repositories\Cobranzas\Contracts\CollectionRepositoryInterface;

class CollectionRepository implements CollectionRepositoryInterface
{

    /*
     * Obtiene los saldos aplicados por el id de la caja y una fecha especifica
     * @param int $boxId
     * @param string $date
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getAppliedBalancesByBox(int $boxId, string $date)
    {
        return GpCobrosAplicacionMonto::query()
            ->from('gp_cobros_aplicacion_montos as gcam')
            ->join('gp_cobros as gcc', 'gcc.id', 'gcam.cobro_id')
            ->join('gp_clientes as gcl1', 'gcl1.id', 'gcc.cliente_id')
            ->where('gcc.caja_id', $boxId)
            ->whereDate('gcc.fecha', $date)
            ->withSum('facturasAplicadas as totalAplicado', 'monto')
            ->select(
                'gcam.*',
                'gcc.caja_id',
                'gcc.numero_recibo',
                'gcc.fecha',
                'gcl1.CLI_RAZON_SOCIAL',
                'gcl1.CLI_RUC'
            )
            ->get();
    }


    /** depositos bancarios */
    public function getDepositsByBox(int $boxId, string $date)
    {
        return GpCobrosDeposito::query()
            ->from('gp_cobros_depositos as gcd')
            ->join('gp_cobros as gcc', 'gcc.id', 'gcd.cobro_id')
            ->join('gp_clientes as gcl', 'gcl.id', 'gcc.cliente_id')
            ->join('gp_caja_bancos as gcb', 'gcb.id', 'gcd.banco_id')
            ->where('gcc.caja_id', $boxId)
            ->whereDate('gcc.fecha', $date)
            ->withSum('depositosFactura as totalAplicado', 'monto')
            ->select(
                'gcd.*',
                'gcd.fecha as fecha_deposito',
                'gcd.mensaje_ws',
                'gcc.numero_recibo',
                'gcc.fecha',
                'gcl.CLI_RAZON_SOCIAL',
                'gcl.CLI_RUC',
                'gcb.CB_DESCRIPCION'
            )
            ->get();
    }


    public function getChequesByBox(int $boxId, string $date, bool $posfechados = false)
    {
        $query = GpCobrosCheques::query()
            ->from('gp_cobros_cheques as gcc')
            ->join('gp_cobros as gc', 'gc.id', 'gcc.cobro_id')
            ->join('gp_clientes as gcl', 'gcl.id', 'gc.cliente_id')
            ->join('gp_caja_bancos as gcb', 'gcb.id', 'gcc.banco_id')
            ->where('gc.caja_id', $boxId)
            ->where('gc.fecha', $date)
            ->withSum('chequesFacturas as totalAplicado', 'monto');

        if ($posfechados) {
            $query->whereColumn('gc.fecha', '<', 'gcc.fecha_vencimiento');
        } else {
            $query->whereColumn('gc.fecha', '=', 'gcc.fecha_vencimiento');
        }
        return $query->select(
            'gcc.*',
            'gc.fecha',
            'gc.numero_recibo',
            'gc.fecha',
            'gcl.CLI_RAZON_SOCIAL',
            'gcl.CLI_RUC',
            'gcb.CB_DESCRIPCION'
        )
            ->get();
    }


    public function getCashPaymentsByBox(int $boxId, string $date)
    {
        return GpCobrosEfectivoMonto::query()
            ->from('gp_cobros_efectivo_montos as gce')
            ->join('gp_cobros as gc', 'gc.id', 'gce.cobro_id')
            ->join('gp_clientes as gcl', 'gcl.id', 'gc.cliente_id')
            ->where('gc.caja_id', $boxId)
            ->whereDate('gc.fecha', $date)
            ->withSum('efectivoFactura as totalAplicado', 'monto')
            ->select(
                'gce.*',
                'gc.caja_id',
                'gc.numero_recibo',
                'gc.fecha',
                'gcl.CLI_RAZON_SOCIAL',
                'gcl.CLI_RUC'
            )
            ->get();
    }


    public function getRetencionsByBox(int $boxId, string $date)
    {
        return GpCobrosRetenciones::query()
            ->from('gp_cobros_retenciones as gcr')
            ->join('gp_cobros as gc', 'gc.id', 'gcr.cobro_id')
            ->join('gp_clientes as gcl', 'gcl.id', 'gc.cliente_id')
            ->where('gc.caja_id', $boxId)
            ->whereDate('gc.fecha', $date)
            ->select(
                'gcr.*',
                'gc.numero_recibo',
                'gc.fecha',
                'gcl.CLI_RAZON_SOCIAL',
                'gcl.CLI_RUC'
            )
            ->get();
    }


    public function getCardPaymentsByBox(int $boxId, string $date)
    {
        return GpCobrosTarjetaMonto::query()
            ->from('gp_cobros_tarjeta_montos as gct')
            ->join('gp_cobros as gc', 'gc.id', 'gct.cobro_id')
            ->join('gp_clientes as gcl', 'gcl.id', 'gc.cliente_id')
            ->where('gc.caja_id', $boxId)
            ->whereDate('gc.fecha', $date)
            ->withSum('cobroEfectivoFactura as totalAplicado', 'monto')
            ->select(
                'gct.*',
                'gc.caja_id',
                'gc.numero_recibo',
                'gc.fecha',
                'gcl.CLI_RAZON_SOCIAL',
                'gcl.CLI_RUC'
            )
            ->get();
    }
}
