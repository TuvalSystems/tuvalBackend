<?php

namespace App\Services\Cobranzas;

use App\Models\GpPuntoVenta;
use App\Models\GpUsuario;
use App\Services\Cobranzas\Contracts\CollectionServiceInterface;

class CollectionService implements CollectionServiceInterface
{

    public function __construct()
    {
        // Inyecta dependencias si es necesario
    }


    public function listPointSalesByUSer(string $ptoVta, GpUsuario $user)
    {
        $ptoVta = GpPuntoVenta::where('PTO_VTA_ID', $ptoVta)->first();
        if (!$ptoVta) {
            throw new \Exception("Punto de venta no encontrado");
        }
        return $user->puntoVentaPermitidos()
        ->where('gp_punto_ventas.id', $ptoVta->id)
        ->get(['gp_punto_ventas.id', 'gp_punto_ventas.PTO_VTA_ID', 'gp_punto_ventas.PTO_VTA_DESCRIPCION']);
    }
}
