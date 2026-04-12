<?php

namespace App\Repositories\User\Contracts;

use App\Models\GpUsuario;

interface UserRepositoryInterface
{
    // Define los métodos de consulta
     public function findUserBoxAssigment(int $userId, int $boxId): ?GpUsuario;
     public function findAssignmentByBox(int $boxId): ?GpUsuario;
}
