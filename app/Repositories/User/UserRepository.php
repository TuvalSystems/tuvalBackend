<?php

namespace App\Repositories\User;

use App\Models\GpUsuario;
use App\Repositories\User\Contracts\UserRepositoryInterface;

class UserRepository implements UserRepositoryInterface
{

    public function findUserBoxAssigment(int $userId, int $boxId): ?GpUsuario
    {
        return GpUsuario::with(['cajasAsignadas' => function ($query) use ($boxId) {
            $query->where('gp_cajas.id', $boxId)->with('puntosVenta');
        }])->find($userId);
    }
}
