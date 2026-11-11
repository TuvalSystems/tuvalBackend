<?php

declare(strict_types=1);

namespace App\Http\Controllers\Reportes;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reportes\ConsultarAprobacionesComercialesRequest;
use App\Services\Reportes\Contracts\CommercialApprovalsServiceInterface;
use App\Traits\ExecuteApiTransactions;
use App\Traits\JsonResponseTrait;
use Illuminate\Http\JsonResponse;

class AprobacionesComercialesController extends Controller
{
    use JsonResponseTrait, ExecuteApiTransactions;

    public function __construct(
        private readonly CommercialApprovalsServiceInterface $service,
    ) {
    }

    public function consultar(ConsultarAprobacionesComercialesRequest $request): JsonResponse
    {
        return $this->handleApiAction(function () use ($request): JsonResponse {
            $data = $request->validated();
            $approvals = $this->service->getApprovals($data['tipo_documento'], (int) $data['documento_id']);

            return $this->successResponse('Consulta de aprobaciones comerciales realizada.', $approvals);
        }, 'stack', 'consultarAprobacionesComerciales');
    }
}
