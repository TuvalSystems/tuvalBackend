<?php

namespace App\Services\Inventario\GuiasRemision\Contracts;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface ReceivedDeliveryNoteServiceInterface
{
    public function types(): array;
    public function getWarehouses(): Collection;
    public function listReceived(array $filters, int $userId): LengthAwarePaginator;
}
