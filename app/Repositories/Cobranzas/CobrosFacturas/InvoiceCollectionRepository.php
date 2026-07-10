<?php

namespace App\Repositories\Cobranzas\CobrosFacturas;

use App\Models\GpCaja;
use App\Models\GpUsuario;
use App\Repositories\Cobranzas\CobrosFacturas\Contracts\InvoiceCollectionRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;

class InvoiceCollectionRepository implements InvoiceCollectionRepositoryInterface
{

    public function queryGetRelationShipUserBox(GpUsuario $user, ?int $pvId = null, ?string $pvCode = null): ?GpCaja
    {
        $query = $user->cajasAsignadas()
            ->orderBy('gp_cajas.id');
        if ($pvId || filled($pvCode)) {
            $query->whereHas('puntosVenta', function (Builder $pointSaleQuery) use ($pvId, $pvCode) {
                if ($pvId) {
                    $pointSaleQuery->where('gp_punto_ventas.id', $pvId);
                }
                if (filled($pvCode)) {
                    $pointSaleQuery->where('gp_punto_ventas.PTO_VTA_ID', $pvCode);
                }
            });
        }
        return $query->first();
    }
}
