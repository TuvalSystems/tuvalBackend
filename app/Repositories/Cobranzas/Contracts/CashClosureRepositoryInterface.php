<?php

namespace App\Repositories\Cobranzas\Contracts;

interface CashClosureRepositoryInterface
{
    public function findByBoxAndDate(int $boxId, string $date, int $userId): bool;
}
