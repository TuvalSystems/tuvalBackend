<?php

namespace App\Repositories\Venta;

use App\Models\GpCotizacion;
use App\Models\GpTempCart;
use App\Models\GpTempCartProveedores;
use App\Repositories\Venta\Contracts\QuotationRepositoryInterface;

class QuotationRepository implements QuotationRepositoryInterface
{
    public function getScheduledReminders()
    {
        $hoy = now()->startOfDay();
        return GpCotizacion::with('detallecotizacion', 'usuario', 'motivosNoVenta')->whereNotNull('cotizacion_venta_motivo_id')
            ->whereDate('fecha_recordatorio', $hoy)
            ->where('recordar', 1)
            ->get();
    }


    /**
     *
     * funciones para llenar cotizacion
     *
     *
     *   */


    public function getGpTempCartFillQuotation($sucursalId, $puntoVentaId, $clienteId, $userId)
    {
        return GpTempCart::where('sucursal_id', $sucursalId)
            ->where('puntodeventa_id', $puntoVentaId)
            ->where('cliente_id', $clienteId)
            ->where('user_id', $userId)
            ->orderBy('orden', 'asc')
            ->get();
    }

    public function getQuotationHeaderById(int $id)
    {
        return GpCotizacion::where('id', $id)->first();
    }


    /**
     * funciones para cart temp
     */

    public function computedMaxOrder(int $clientId, string $ptoVta, int $sucursalId){
        return GpTempCart::where('sucursal_id',$sucursalId)
        ->where('cliente_id', $clientId)
        ->where('puntodeventa_id', $ptoVta)
        ->max('orden') ;
    }

    public function deleteProviderRegisterTemp($tempId){
        GpTempCartProveedores::where('id_tempcart', $tempId)->delete();
    }

    public function updateTempCartArtValor($idTempCart, $artValor){
        GpTempCart::where('id', $idTempCart)
        ->update(['ART_VALOR' => $artValor]);
    }
}
