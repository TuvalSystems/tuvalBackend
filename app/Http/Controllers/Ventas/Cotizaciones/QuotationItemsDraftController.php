<?php

namespace App\Http\Controllers\Ventas\Cotizaciones;

use App\Http\Controllers\Controller;
use App\Http\Requests\Venta\Cotizaciones\StoreQuotationItemsDraftRequest;
use App\Services\Ventas\Cotizaciones\Contracts\QuotationItemsDraftServiceInterface;
use App\Traits\ExecuteApiTransactions;
use App\Traits\JsonResponseTrait;
use Illuminate\Http\JsonResponse;

class QuotationItemsDraftController extends Controller
{
    use ExecuteApiTransactions, JsonResponseTrait;

    public function __construct(protected readonly QuotationItemsDraftServiceInterface $service) {}

    public function store(StoreQuotationItemsDraftRequest $request): JsonResponse
    {
        return $this->handleApiAction(function () use ($request) {
            $result = $this->service->create($request->validated(), $request->user());

            return $this->successResponse('Cotización borrador disponible para revisión.', $result, $result['reutilizado'] ? 200 : 201);
        }, 'cotizaciones', 'crear borrador por items');
    }
}
