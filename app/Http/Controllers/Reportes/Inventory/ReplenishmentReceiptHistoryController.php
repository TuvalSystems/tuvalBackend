<?php

declare(strict_types=1);

namespace App\Http\Controllers\Reportes\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reportes\Inventory\ReplenishmentReceiptHistoryRequest;
use App\Services\Reportes\Inventory\Contracts\ReplenishmentReceiptHistoryServiceInterface;
use App\Traits\ExecuteApiTransactions;
use App\Traits\JsonResponseTrait;
use Illuminate\Http\JsonResponse;

class ReplenishmentReceiptHistoryController extends Controller
{
    use JsonResponseTrait, ExecuteApiTransactions;

    public function __construct(
        private readonly ReplenishmentReceiptHistoryServiceInterface $service,
    ) {}

    public function index(ReplenishmentReceiptHistoryRequest $request): JsonResponse
    {
        return $this->handleApiAction(function () use ($request): JsonResponse {
            $filters = $request->validated();
            $history = $this->service->getHistory($filters['periodo'], $filters['numero_reposicion'] ?? null);

            return $this->successResponse('Consulta de historial de reposiciones realizada correctamente.', $history);
        }, 'stack', 'replenishmentReceiptHistory');
    }
}
