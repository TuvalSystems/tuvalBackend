<?php

namespace App\Services\Cobranzas;

use App\Models\GpCierreCaja;
use App\Models\GpPuntoVenta;
use App\Models\GpUsuario;
use App\Services\Cobranzas\Contracts\CollectionServiceInterface;

class CollectionService implements CollectionServiceInterface
{

    public function __construct()
    {
        // Inyecta dependencias si es necesario
    }


    public function listPointSalesByUSer(GpUsuario $user)
    {
        return $user->puntoVentaPermitidos()
            ->get(['gp_punto_ventas.id', 'gp_punto_ventas.PTO_VTA_ID', 'gp_punto_ventas.PTO_VTA_DESCRIPCION'])->makeHidden('pivot');
    }


    public function listBoxesByPointSales(string $pv, GpUsuario $user)
    {
        $ptoVta = GpPuntoVenta::where('PTO_VTA_ID', $pv)->first();
        if (!$ptoVta) {
            throw new \RuntimeException("Punto de venta no encontrado");
        }
        return $ptoVta->cajasDisponibles()
            ->whereHas('operariosAsignados', function ($query) use ($user) {
                $query->where('gp_usuarios.id', $user->id);
            })
            ->get(['gp_cajas.id', 'CAJA_CODIGO', 'CAJA_DESCRIPCION'])
            ->makeHidden('pivot');
    }


    public function getBoxClosureByDate(int $boxId, GpUsuario $user, string $date)
    {
        return GpCierreCaja::where('caja_id', $boxId)
            ->whereHas('caja', function ($query) use ($user) {
                $query->whereHas('operariosAsignados', function ($q) use ($user) {
                    $q->where('gp_usuarios.id', $user->id);
                });
            })
            ->where('fecha', $date)
            ->where('estado', 'cerrado')
            ->get();
    }
}
