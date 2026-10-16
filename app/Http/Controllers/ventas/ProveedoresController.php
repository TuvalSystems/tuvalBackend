<?php

namespace App\Http\Controllers\ventas;

use App\Http\Controllers\Controller;
use App\Services\Venta\Proveedores\Contracts\CarrierDriverServiceInterface;
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
        protected readonly CarrierDriverServiceInterface $carrierService,
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

    public function listaProveedoresTransportistas(): JsonResponse
    {
        return $this->handleApiAction(function (): JsonResponse {
            $lista = $this->providerService->listProviderTypeCarrier();
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

    /**
     *  transporte proveedores
     */

    public function listaChoferTransportista(Request $request): JsonResponse
    {
        return $this->handleApiAction(function () use ($request): JsonResponse {
            $filters  = data_get($request, 'filters', []);
            $lista = $this->carrierService->listCarrierDrivers($filters);
            return $this->successResponse('Información Obtenida correctamente.!', $lista);
        }, self::LOG_CHANNEL, 'listaChoferTransportista');
    }

    public function crearRegistroChoferTransportista(Request $request): JsonResponse
    {
        return $this->handleApiAction(function () use ($request): JsonResponse {
            $data = $request->input('form', []);
            $user = $request->user();
            $this->carrierService->createRegisterCarrier($data, $user);
            return $this->buildSuccessResponse('Registro creado exitosamente.!');
        }, self::LOG_CHANNEL, 'crearRegistroChoferTransportista');
    }

    public function obtenerDetalleChoferTransportista(Request $request): JsonResponse
    {
        return $this->handleApiAction(function () use ($request): JsonResponse {
            $validated = $request->validate([
                'id' => 'required|integer'
            ]);
            $id = data_get($validated, 'id');
            $info = $this->carrierService->getDetailCarrierDriver($id);
            return $this->successResponse('Información obtenida correctamente.!', $info);
        }, self::LOG_CHANNEL, 'obtenerDetalleChoferTransportista');
    }

    public function actualizarRegistroChoferTransportista(Request $request, int $id): JsonResponse
    {
        return $this->handleApiAction(function () use ($request, $id): JsonResponse {
            $data =  $request->input('form', []);
            $user = $request->user();
            $this->carrierService->updateRegisterCarrier($id, $data, $user);
            return $this->buildSuccessResponse('Registro Actualizado correctamente.!');
        }, self::LOG_CHANNEL, 'actualizarRegistroChoferTransportista');
    }

    public function actualizarEstadoChofeTransportista(int $id): JsonResponse
    {
        return $this->handleApiAction(function () use ($id): JsonResponse {
            $this->carrierService->updateStatusCarrier($id);
            return $this->buildSuccessResponse('Registro Actualizado correctamente.!');
        }, self::LOG_CHANNEL, 'actualizarEstadoChofeTransportista');
    }


    public function validarIdentificacionChoferTransportista(Request $request): JsonResponse
    {
        return $this->handleApiAction(function () use ($request): JsonResponse {
            $validate = $request->validate([
                'id'                => 'nullable|integer|exists:chofer_transportistas,id',
                'identificacion'    => 'required|string'
            ]);
            $id = data_get($validate, 'id');
            $identificacion = data_get($validate, 'identificacion');
            $existe = $this->carrierService->validateCarrierIdentify($identificacion, $id);
            if ($existe) {
                return $this->buildExistResponse(false, 'El numero de identificación ya se encuentra registrado con anterioridad', 200);
            }
            return $this->buildExistResponse(true, 'El registro no existe, continue...', 200);
        }, self::LOG_CHANNEL, 'validarIdentificacionChoferTransportista');
    }


    public function obtenerDetalleProveedor(Request $request): JsonResponse
    {
        return $this->handleApiAction(function () use ($request) : JsonResponse{
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
