<?php

namespace App\Services\Aprobaciones\Utils\Contracts;

use App\Models\GpDatosItemsL;
use Illuminate\Database\Eloquent\Collection;

interface ApprovalFunctionsServiceInterface
{
    public function requiredApprovalResponse(string $motivo);
    public function requiredNotApprovalResponse(string $motivo);
    public function finalizeApprovalReasons(array $reasons);

    public function getTypeArticleLQuotationOrOrder(string $codArticulo): ?GpDatosItemsL;

    public function getNivelFormaPago($columna, $codigo);
    public function getPorcentilDiscount($columna, $codigo);
    public function getPorcentilDiscountCommercialCliente($ruc): float;

    public function getProviderToValidateMargen($prov);

    public function getApprovalRulesForUserByZone(int $userId, array $zonas, int $typeSellerId): Collection;
    public function getApprovalRulesByUser(int $userId): Collection;

    public function calculatePriceVariationPercentage(float $originalPrice, float $currentPrice): float;

    public function calculateDiscountChange(float $originalDiscount, float $currentDiscount): float;
    public function getClientSegments(array $rucs): array;
}
