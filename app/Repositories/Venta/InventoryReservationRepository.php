<?php

namespace App\Repositories\Venta;

use App\Models\GpCotizacion;
use App\Models\GpCotizacionDetalle;
use App\Models\reservas\GpTrasladosReservas;
use App\Repositories\Venta\Contracts\InventoryReservationRepositoryInterface;
use Illuminate\Support\Facades\DB;

class InventoryReservationRepository implements InventoryReservationRepositoryInterface
{

    public function getDetailsForTransferReservation(GpCotizacion $quote)
    {
        return GpCotizacionDetalle::query()->from('gp_cotizacion_detalles as cd')->where('cd.cotizacion_id', $quote->id)
            ->join('gp_cotizacions as c', 'c.id', '=', 'cd.cotizacion_id')
            ->join('gp_reservas as r', 'r.cotizacion_id', '=', 'c.id')
            ->join('gp_cotizacion_detalle_stocks as cds', 'cds.detalle_id', '=', 'cd.id')
            ->where('cds.seleccionado', '>', 0)
            ->where('cds.STO_ART_BODEGA', '!=', $quote->BOD_BODEGA)
            ->select([
                'cd.*',
                'r.id as reserva_id',
                'cd.cotizacion_id',
                'cds.id as stock_id',
                'c.BOD_BODEGA as bodega_destino',
                'cds.STO_ART_BODEGA as bodega_origen',
                'cds.seleccionado',
            ])
            ->get();
    }


    public function checkExistingReservationTransfer(GpCotizacion $quote)
    {
        return GpCotizacionDetalle::query()->from('gp_cotizacion_detalles as cd')->where('cd.cotizacion_id', $quote->id)
            ->join('gp_cotizacion_detalle_stocks as cds', 'cds.detalle_id', '=', 'cd.id')
            ->where('cds.seleccionado', '>', 0)
            ->where('cds.STO_ART_BODEGA', '!=', $quote->BOD_BODEGA)
            ->exists();
    }

    public function getAllTransferReservedByWarehouses($warehouse)
    {
        return GpTrasladosReservas::query()->from('gp_traslados_reservas as tr')
            ->join('gp_cotizacions as c', 'c.id', '=', 'tr.cotizacion_id')
            ->leftJoin('gp_bodegas as b', 'b.BOD_BODEGA', '=', 'c.BOD_BODEGA')
            ->where('tr.bodega_origen', $warehouse)
            ->where(function ($q) {
                $q->whereNull('tr.completado')->orWhere('tr.completado', 0);
            })
            ->select([
                'tr.*',
                'b.BOD_DESCRIPCION as bodega_destino_desc',
                'c.PTO_VTA_ID as ptovtaId',
            ])->get();
    }

    public function getAllTransferReservedByOriginAndDestination($waOrigin, $waDestination)
    {
        return GpTrasladosReservas::query()->from('gp_traslados_reservas as tr')
            ->join('gp_cotizacions as c', 'c.id', '=', 'tr.cotizacion_id')
            ->leftJoin('gp_bodegas as b', 'b.BOD_BODEGA', '=', 'c.BOD_BODEGA')
            ->where('tr.bodega_origen', $waOrigin)
            ->where('tr.bodega_destino', $waDestination)
            ->where(function ($q) {
                $q->whereNull('tr.completado')->orWhere('tr.completado', 0);
            })
            ->select([
                'tr.*',
                'b.BOD_DESCRIPCION as bodega_destino_desc',
                'c.cotizacion',
            ])->get();
    }


   



}
