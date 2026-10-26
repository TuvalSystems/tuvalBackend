<?php

namespace App\Repositories\Venta;

use App\Models\Ventas\Cotizaciones\GpCotizacion;
use App\Models\Ventas\Cotizaciones\GpCotizacionDetalle;
use App\Models\Ventas\Reservas\GpGuiaRemisionReserva;
use App\Models\Ventas\Reservas\GpTrasladosReservas;
use App\Repositories\Venta\Contracts\InventoryReservationRepositoryInterface;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

class InventoryReservationRepository implements InventoryReservationRepositoryInterface
{

    public function getDetailsForTransferReservation(GpCotizacion $quote): EloquentCollection
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


    public function checkExistingReservationTransfer(GpCotizacion $quote): bool
    {
        return GpCotizacionDetalle::query()->from('gp_cotizacion_detalles as cd')->where('cd.cotizacion_id', $quote->id)
            ->join('gp_cotizacion_detalle_stocks as cds', 'cds.detalle_id', '=', 'cd.id')
            ->where('cds.seleccionado', '>', 0)
            ->where('cds.STO_ART_BODEGA', '!=', $quote->BOD_BODEGA)
            ->exists();
    }

    public function getAllTransferReservedByWarehouses(?string $warehouse): EloquentCollection
    {
        return GpTrasladosReservas::query()->from('gp_traslados_reservas as tr')
            ->leftJoin('gp_bodegas as b', 'b.BOD_BODEGA', '=', 'tr.bodega_destino')
            ->join('gp_relacion_punto_venta_bodegas as ptovta', 'ptovta.PTO_VTA_BOD_BODEGA', '=', 'tr.bodega_destino')
            ->where('tr.bodega_origen', $warehouse)
            ->where(function ($q) {
                $q->whereNull('tr.completado')->orWhere('tr.completado', 0);
            })
            ->select([
                'tr.*',
                'b.BOD_DESCRIPCION as bodega_destino_desc',
                'ptovta.PTO_VTA_BOD_PUNTO_VENTA as ptovtaId',
            ])->get();
    }

    public function getAllTransferReservedByOriginAndDestination(string $waOrigin, string $waDestination, ?string $quotation = null): EloquentCollection
    {
        return GpTrasladosReservas::query()->from('gp_traslados_reservas as tr')
            ->join('gp_cotizacions as c', 'c.id', '=', 'tr.cotizacion_id')
            ->join('gp_reservas as r', 'r.id', '=', 'tr.reserva_id')
            ->join('gp_usuarios as u', 'u.id', '=', 'r.user_ingreso_reserva_id')
            ->join('gp_articulos as a', 'a.ART_CODIGO', '=', 'tr.art_code')
            ->leftJoin('gp_bodegas as b', 'b.BOD_BODEGA', '=', 'c.BOD_BODEGA')
            ->where('tr.bodega_origen', $waOrigin)
            ->where('tr.bodega_destino', $waDestination)
            ->where(function ($q) {
                $q->whereNull('tr.completado')->orWhere('tr.completado', 0);
            })
            ->when($quotation, function ($q) use ($quotation) {
                $q->where('c.cotizacion', $quotation);
            })
            ->select([
                'tr.*',
                'b.BOD_DESCRIPCION as bodega_destino_desc',
                'u.USERID as user_solicitud_reserva',
                'a.ART_DECIMAL_CANTIDAD as art_decimal_cantidad',
                'c.cotizacion',
            ])->get();
    }


    public function findCountPendingGuidesByOriginWarehouse(?string $bodOrigin): EloquentCollection
    {
        return GpGuiaRemisionReserva::query()
            ->from('gp_guia_remision_reservas as gpgr')
            ->join('gp_bodegas', 'gpgr.bodega_destino', '=', 'gp_bodegas.BOD_BODEGA')
            ->join('gp_relacion_punto_venta_bodegas as ptovta', 'ptovta.PTO_VTA_BOD_BODEGA', '=', 'gpgr.bodega_destino')
            ->where('gpgr.bodega_origen', $bodOrigin)
            ->where('gpgr.estado', 'pendiente')
            ->groupBy(
                'gpgr.bodega_destino',
                'gp_bodegas.BOD_DESCRIPCION',
                'ptovta.PTO_VTA_BOD_PUNTO_VENTA'
            ) // <--- Aquí agrupamos
            ->selectRaw('gpgr.bodega_destino, gp_bodegas.BOD_DESCRIPCION as bodega_destino_desc, count(*) as total, ptovta.PTO_VTA_BOD_PUNTO_VENTA as ptovtaId')
            ->get()
            ->keyBy('bodega_destino');
    }

    public function findGuidesPendingsByOriginWarehouse(string $bodOrigin, string $destId): EloquentCollection
    {
        return GpGuiaRemisionReserva::query()
            ->from('gp_guia_remision_reservas as gpgr')
            ->join('gp_bodegas', 'gpgr.bodega_destino', '=', 'gp_bodegas.BOD_BODEGA')
            ->where('gpgr.bodega_origen', $bodOrigin)
            ->where('gpgr.bodega_destino', $destId)
            ->where('gpgr.estado', 'pendiente')
            ->select('gpgr.id', 'gpgr.numero_guia', 'gpgr.fecha_salida', 'gpgr.razon_social_transportista', 'gpgr.placa')
            ->orderByDesc('gpgr.created_at')
            ->get();
    }







}
