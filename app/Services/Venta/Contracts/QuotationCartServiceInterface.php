<?php

namespace App\Services\Venta\Contracts;

use App\Models\GpTempCart;
use App\Models\GpUsuario;

interface QuotationCartServiceInterface
{
    public function createTempItem(int $clientId, string $ptoVta, int $sucursalId, array $article, GpUsuario $user): GpTempCart;

    public function updateDescriptionItemTemp(int $tempId, string $description);
    public function updateCheckAlternativeTemp(int $tempId, bool $checkAlternative);
    public function updateQuantityTemp(int $tempId, float $quantity);
    public function updatePriceTemp(int $tempId, float $price, float $modifyPrice);
    public function updateKgTemp(int $tempId, float $kg);
    public function updateStockTemp(int $tempId, int $qty);
    public function updateDiscountTemp(int $tempId, float $discount);
    public function destroyTempItem(int $tempId);
    public function updateModifyPriceTemp(int $tempId, float $modifyPrice);

    public function saveProviderQuotationTemp(array $data);




}
