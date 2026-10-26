<?php

namespace App\Repositories\User;

use App\Models\Admin\Usuarios\GpUsuario;
use App\Repositories\User\Contracts\UserRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class UserRepository implements UserRepositoryInterface
{

    public function findUserBoxAssigment(int $userId, int $boxId): ?GpUsuario
    {
        return GpUsuario::with(['cajasAsignadas' => function ($query) use ($boxId) {
            $query->where('gp_cajas.id', $boxId)->with('puntosVenta');
        }])->find($userId);
    }

    public function findAssignmentByBox(int $boxId): ?GpUsuario
    {
        return GpUsuario::whereHas('cajasAsignadas', function ($query) use ($boxId) {
            $query->where('gp_cajas.id', $boxId);
        })->first();
    }

    public function findAssignmentByBoxAndSalesPoint(int $userId, int $boxId, int $pvId, ?string $pvCode = null): ?GpUsuario
    {
        return GpUsuario::query()
            ->whereKey($userId)
            // ->whereHas('puntoVentaPermitidos', function ($query) use ($pvId, $pvCode) {
            //     $this->applySalesPointFilters($query, $pvId, $pvCode);
            // })
            ->whereHas('cajasAsignadas', function ($query) use ($boxId, $pvId, $pvCode) {
                $query->where('gp_cajas.id', $boxId)
                    ->where('gp_cajas.estado', 1)
                    ->whereHas('puntosVenta', function ($pointSaleQuery) use ($pvId, $pvCode) {
                        $this->applySalesPointFilters($pointSaleQuery, $pvId, $pvCode);
                    });
            })
            ->with(['cajasAsignadas' => function ($query) use ($boxId, $pvId, $pvCode) {
                $query->where('gp_cajas.id', $boxId)
                    ->where('gp_cajas.estado', 1)
                    ->with(['puntosVenta' => function ($pointSaleQuery) use ($pvId, $pvCode) {
                        $this->applySalesPointFilters($pointSaleQuery, $pvId, $pvCode);
                        $pointSaleQuery->select(
                            'gp_punto_ventas.id',
                            'gp_punto_ventas.PTO_VTA_ID',
                            'gp_punto_ventas.PTO_VTA_DESCRIPCION'
                        );
                    }]);
            }])
            ->first();
    }

    private function applySalesPointFilters(Builder|BelongsToMany $query, int $pvId, ?string $pvCode = null): void
    {
        $query->where('gp_punto_ventas.id', $pvId);

        if (filled($pvCode)) {
            $query->where('gp_punto_ventas.PTO_VTA_ID', $pvCode);
        }
    }
}
