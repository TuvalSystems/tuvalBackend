<?php

declare(strict_types=1);

namespace App\Services\Reportes\Contracts;

interface SalesBranchComparisonServiceInterface
{
    public function getComparison(string $startDate, string $endDate, ?string $branches = null): array;
}
