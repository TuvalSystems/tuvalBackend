<?php

namespace App\Repositories\Venta\Contracts;

use Illuminate\Database\Eloquent\Builder;

interface QuotationRepositoryInterface
{
    public function getScheduledReminders();
    public function fetchPendingQuotes(int $ptoVta): Builder;
}
