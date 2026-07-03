<?php
namespace App\Services\Cobranzas\Depositos\Contracts;

use Illuminate\Pagination\LengthAwarePaginator;

interface HistoricalDepositProcessorServiceInterface
{
    // Define los métodos
    public function fetchListHistoricalDeposits(array $filters) :LengthAwarePaginator;
}
