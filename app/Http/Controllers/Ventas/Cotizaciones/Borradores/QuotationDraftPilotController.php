<?php

namespace App\Http\Controllers\Ventas\Cotizaciones\Borradores;

use App\Http\Controllers\Controller;
use App\Http\Requests\Venta\Cotizaciones\StoreQuotationDraftPilotRequest;
use App\Services\Ventas\Cotizaciones\Borradores\QuotationDraftPilotService;
use App\Traits\ExecuteApiTransactions;
use App\Traits\JsonResponseTrait;
use Illuminate\Http\JsonResponse;

class QuotationDraftPilotController extends Controller
{
    use ExecuteApiTransactions, JsonResponseTrait;

    public function __construct(protected readonly QuotationDraftPilotService $pilot) {}

    public function store(StoreQuotationDraftPilotRequest $request): JsonResponse
    {
        return $this->handleApiAction(function () use ($request): JsonResponse {
            $result = $this->pilot->create($request->validated(), $request->user());
            $data = $result['data'];
            $message = $data['id'] === null
                ? 'No se encontraron artículos disponibles para crear el borrador.'
                : "Cotización borrador {$data['numero']} creada.";

            return $this->successResponse($message, [...$data, 'replayed' => $result['replayed']],
                $result['replayed'] || $data['id'] === null ? 200 : 201);
        }, 'cotizaciones', 'crear borrador piloto autenticado');
    }
}
