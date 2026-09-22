<?php

namespace App\Http\Controllers\ventas;

use App\Http\Controllers\Controller;
use App\Services\Venta\Proveedores\Contracts\ProviderServiceInterface;
use App\Traits\ExecuteApiTransactions;
use App\Traits\JsonResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProveedoresController extends Controller
{
    use JsonResponseTrait, ExecuteApiTransactions;
    private const LOG_CHANNEL = 'cotizacion';

    public function __construct(
        protected readonly ProviderServiceInterface $providerService
    ) {}


    public function listaProveedores(Request $request): JsonResponse
    {
        return $this->handleApiAction(function () use ($request): JsonResponse {
            $filters  = data_get($request, 'filters', []);
            $user     = $request->user();
            $lista    = $this->providerService->listProviders($filters, $user);
            return $this->successResponse('Lista Obtenida Correctamente.!', $lista);
        }, self::LOG_CHANNEL, 'listaProveedores');
    }

    public function actualizarEstadoTransportista(int $id, Request $request): JsonResponse
    {
        return $this->handleApiAction(function () use ($id, $request): JsonResponse {
            $user = $request->user();
            $this->providerService->updateCarrierStatus($id, $user);
            return $this->buildSuccessResponse('Registro actualizado correctamente.!');
        }, self::LOG_CHANNEL, 'actualizarEstadoTransportista');
    }

    public function actualizarEstadoProveedor(int $id, Request $request): JsonResponse
    {
        return $this->handleApiAction(function () use ($id, $request): JsonResponse {
            $user = $request->user();
            $this->providerService->updateStatus($id, $user);
            return $this->buildSuccessResponse('Registro actualizado correctamente.!');
        }, self::LOG_CHANNEL, 'actualizarEstadoProveedor');
    }
}
