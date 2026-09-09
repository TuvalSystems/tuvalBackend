<?php
namespace App\Services\Imports\Contracts;

use Illuminate\Support\Collection;

interface QuoteImportsInterface
{
    // Define los métodos
      public function getQuotesByUpdate(string $start, string $end);
       public function markQuotesAsInProcess(array $ids): int;
       public function updateSqlServerQuotesFromPayload(Collection $payload);
}
