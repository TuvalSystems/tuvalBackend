<?php

namespace App\Http\Controllers\Ventas;

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
        protected readonly ProviderServiceInterface $providerService,
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

    public function listaProveedoresTransportistas(Request $request): JsonResponse
    {
        return $this->handleApiAction(function () use ($request): JsonResponse {
            $pvId = $request->input('pv_id');
            $lista = $this->providerService->listProviderTypeCarrier($pvId);
            return $this->successResponse('Información Obtenida correctamente.!', $lista);
        }, self::LOG_CHANNEL, 'listaProveedoresTransportistas');
    }

    public function listaProveedoresActivos(): JsonResponse
    {
        return $this->handleApiAction(function (): JsonResponse {
            $lista = $this->providerService->getAllProviders();
            return $this->successResponse('Información Obtenida correctamente.!', $lista);
        }, self::LOG_CHANNEL, 'listaProveedoresActivos');
    }

    public function obtenerDetalleProveedor(Request $request): JsonResponse
    {
        return $this->handleApiAction(function () use ($request): JsonResponse {
            $validate = $request->validate([
                'id'               => 'required|exists:gp_proveedors,id',
            ]);
            $id = data_get($validate, 'id');
            $detalle = $this->providerService->getDetailProvider($id);
            return $this->successResponse('Detalle obtenido correctamente', $detalle);
        }, self::LOG_CHANNEL, 'obtenerDetalleProveedor');
    }

    public function sincronizarPuntoVentaPorProveedor(Request $request): JsonResponse
    {
        return $this->handleApiAction(function () use ($request): JsonResponse {
            $validate = $request->validate([
                'id'               => 'required|exists:gp_proveedors,id',
            ]);
            $id     = data_get($validate, 'id');
            $pvIds  = $request->input('punto_venta_ids');
            $prov   = $this->providerService->syncPotinSalesByProvider($id, $pvIds);
            return $this->successResponse('Proveedor sincronizado correctamente', $prov);
        }, self::LOG_CHANNEL, 'sincronizarPuntoVentaPorProveedor');
    }


}
