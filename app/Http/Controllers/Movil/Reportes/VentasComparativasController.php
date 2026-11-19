<?php

declare(strict_types=1);

namespace App\Http\Controllers\Movil\Reportes;

use App\Http\Controllers\Controller;
use App\Models\Admin\Perfiles\Perfiles;
use App\Services\Reportes\Contracts\SalesBranchComparisonServiceInterface;
use App\Traits\ExecuteApiTransactions;
use App\Traits\JsonResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VentasComparativasController extends Controller
{
    use ExecuteApiTransactions, JsonResponseTrait;

    public function __construct(private readonly SalesBranchComparisonServiceInterface $service)
    {
    }

    public function index(Request $request): JsonResponse
    {
        return $this->handleApiAction(function () use ($request): JsonResponse {
            $profile = $request->user()->perfilApp;
            if (!$profile instanceof Perfiles || !$profile->isApp() || !$profile->isActive()
                || trim((string) $profile->name) !== 'Administrador') {
                return $this->errorResponse('No tiene permiso para consultar este reporte.', null, 403);
            }

            $profiles = Perfiles::query()->platform(Perfiles::PLATFORM_APP)->active()
                ->get(['id', 'name'])
                ->filter(static fn (Perfiles $entry): bool => strcasecmp(trim((string) $entry->name), 'Administrador') === 0);

            if ($profiles->count() !== 1 || !$profiles->first()?->is($profile)) {
                return $this->errorResponse('No tiene permiso para consultar este reporte.', null, 403);
            }

            $validated = $request->validate([
                'FechaIni' => ['required', 'date_format:Y-m-d', 'after_or_equal:0001-01-01'],
                'FechaFin' => ['required', 'date_format:Y-m-d', 'after_or_equal:FechaIni', 'before_or_equal:9999-12-31'],
                'Sucursales' => ['sometimes', 'required', 'string', 'max:79'],
            ]);

            $data = $this->service->getComparison(
                $validated['FechaIni'],
                $validated['FechaFin'],
                $validated['Sucursales'] ?? null
            );

            return $this->successResponse('Comparativo de ventas obtenido.', $data);
        }, 'stack', self::class . '@index');
    }
}
