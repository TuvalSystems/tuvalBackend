<?php

declare(strict_types=1);

namespace App\Http\Controllers\Reportes;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reportes\ConsultarAprobacionesComercialesRequest;
use App\Services\Reportes\Contracts\AprobacionesComercialesServiceInterface;
use App\Traits\JsonResponseTrait;
use Illuminate\Http\JsonResponse;
use Throwable;

class AprobacionesComercialesController extends Controller
{
    use JsonResponseTrait;

    public function __construct(
        private readonly AprobacionesComercialesServiceInterface $service,
    ) {
    }

    public function consultar(ConsultarAprobacionesComercialesRequest $request): JsonResponse
    {
        $data = $request->validated();

        try {
            $aprobaciones = $this->service->consultar($data['tipo_documento'], (int) $data['documento_id']);
        } catch (Throwable $exception) {
            report($exception);

            return $this->errorResponse('No se pudieron consultar las aprobaciones comerciales.');
        }

        return $this->successResponse('Consulta de aprobaciones comerciales realizada.', $aprobaciones);
    }
}
