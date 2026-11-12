<?php

namespace App\Http\Controllers\Ventas\Cotizaciones\Borradores;

use App\Http\Controllers\Controller;
use App\Http\Requests\Venta\Cotizaciones\ExtractQuotationImagesRequest;
use App\Services\Ventas\Cotizaciones\Borradores\QuotationImageExtractionService;
use App\Traits\ExecuteApiTransactions;
use App\Traits\JsonResponseTrait;
use Illuminate\Http\JsonResponse;

class QuotationImageExtractionController extends Controller
{
    use ExecuteApiTransactions, JsonResponseTrait;

    public function __construct(protected readonly QuotationImageExtractionService $extraction) {}

    public function extract(ExtractQuotationImagesRequest $request): JsonResponse
    {
        return $this->handleApiAction(fn (): JsonResponse => $this->successResponse(
            'Detalle extraído. Revise y confirme los datos antes de crear el borrador.',
            $this->extraction->extract($request->validated(), $request->user())
        ), 'cotizaciones', 'extraer detalle desde imágenes');
    }
}
