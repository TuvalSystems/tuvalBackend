<?php

declare(strict_types=1);

namespace App\Services\Reportes;

use App\Services\Integraciones\Api\ApiGpService;
use App\Services\Reportes\Contracts\SalesBranchComparisonServiceInterface;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class SalesBranchComparisonService implements SalesBranchComparisonServiceInterface
{
    private const BRANCHES = 'FAC-CAL,FAC-MAT,FAC-CUE,FAC-MAN,FAC-SDM,FAC-DAU,FAC-DUR,FAC-QNT,FAC-QSR,FAC-MAC';

    public function __construct(private readonly ApiGpService $apiGp)
    {
    }

    public function getComparison(string $startDate, string $endDate, ?string $branches = null): array
    {
        if ($branches !== null) {
            $codes = explode(',', $branches);
            $allowed = explode(',', self::BRANCHES);

            if (count(array_unique($codes)) !== count($codes) || array_diff($codes, $allowed) !== []) {
                throw ValidationException::withMessages([
                    'Sucursales' => 'Indique códigos de sucursales válidos sin repetir.',
                ]);
            }
        }

        $params = [
            'FechaIni' => $startDate,
            'FechaFin' => $endDate,
            'Sucursales' => $branches ?? self::BRANCHES,
        ];

        $response = $this->apiGp->getSalesBranchComparison($params);

        if (!is_array($response) || array_key_exists('status', $response)
            || ($response['success'] ?? null) !== true
            || !is_array($response['data'] ?? null)
            || !array_is_list($response['data'])) {
            throw new HttpException(502, 'No se pudo consultar el comparativo de ventas.');
        }

        return $response['data'];
    }
}
