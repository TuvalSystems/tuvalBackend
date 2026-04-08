<?php

namespace App\Repositories\Cobranzas;

use App\Models\GpCierreCaja;
use App\Repositories\Cobranzas\Contracts\CashClosureRepositoryInterface;

class CashClosureRepository implements CashClosureRepositoryInterface
{

    public function findByBoxAndDate(int $boxId, string $date, int $userId) : bool
    {
        return GpCierreCaja::where('caja_id', $boxId)
            ->whereHas('caja', function ($query) use ($userId) {
                $query->whereHas('operariosAsignados', function ($q) use ($userId) {
                    $q->where('gp_usuarios.id', $userId);
                });
            })
            ->where('fecha', $date)
            ->where('estado', 'cerrado')
            ->exists();
    }
}
