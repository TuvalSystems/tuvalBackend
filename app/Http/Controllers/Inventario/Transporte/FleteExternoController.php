<?php

namespace App\Http\Controllers\Inventario\Transporte;

use App\Http\Controllers\Controller;
use App\Services\Venta\Proveedores\Contracts\ExternalFreightServiceInterface;
use App\Traits\ExecuteApiTransactions;
use App\Traits\JsonResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FleteExternoController extends Controller
{
    use JsonResponseTrait, ExecuteApiTransactions;

    private const LOG_CHANNEL = 'cotizacion';

    public function __construct(
        protected readonly ExternalFreightServiceInterface $externalFreightService,
    ) {}

    /**
     *  transporte proveedores
     */

    public function listaFleteExterno(Request $request): JsonResponse
    {
        return $this->handleApiAction(function () use ($request): JsonResponse {
            $filters  = data_get($request, 'filters', []);
            $lista = $this->externalFreightService->listExternalFreights($filters);
            return $this->successResponse('Información Obtenida correctamente.!', $lista);
        }, self::LOG_CHANNEL, 'listaFleteExterno');
    }

    public function crearRegistroFleteExterno(Request $request): JsonResponse
    {
        return $this->handleApiAction(function () use ($request): JsonResponse {
            $data = $request->input('form', []);
            $user = $request->user();
            $this->externalFreightService->createExternalFreight($data, $user);
            return $this->buildSuccessResponse('Registro creado exitosamente.!');
        }, self::LOG_CHANNEL, 'crearRegistroFleteExterno');
    }

    public function obtenerDetalleFleteExterno(Request $request): JsonResponse
    {
        return $this->handleApiAction(function () use ($request): JsonResponse {
            $validated = $request->validate([
                'id' => 'required|integer'
            ]);
            $id = data_get($validated, 'id');
            $info = $this->externalFreightService->getExternalFreightDetail($id);
            return $this->successResponse('Información obtenida correctamente.!', $info);
        }, self::LOG_CHANNEL, 'obtenerDetalleFleteExterno');
    }

    public function actualizarRegistroFleteExterno(Request $request, int $id): JsonResponse
    {
        return $this->handleApiAction(function () use ($request, $id): JsonResponse {
            $data =  $request->input('form', []);
            $user = $request->user();
            $this->externalFreightService->updateExternalFreight($id, $data, $user);
            return $this->buildSuccessResponse('Registro Actualizado correctamente.!');
        }, self::LOG_CHANNEL, 'actualizarRegistroFleteExterno');
    }

    public function actualizarEstadoFleteExterno(int $id): JsonResponse
    {
        return $this->handleApiAction(function () use ($id): JsonResponse {
            $this->externalFreightService->updateExternalFreightStatus($id);
            return $this->buildSuccessResponse('Registro Actualizado correctamente.!');
        }, self::LOG_CHANNEL, 'actualizarEstadoFleteExterno');
    }


    public function validarIdentificacionFleteExterno(Request $request): JsonResponse
    {
        return $this->handleApiAction(function () use ($request): JsonResponse {
            $validate = $request->validate([
                'id'                => 'nullable|integer|exists:flete_externos,id',
                'identificacion'    => 'required|string'
            ]);
            $id = data_get($validate, 'id');
            $identificacion = data_get($validate, 'identificacion');
            $existe = $this->externalFreightService->validateExternalFreightIdentity($identificacion, $id);
            if ($existe) {
                return $this->buildExistResponse(false, 'El numero de identificación ya se encuentra registrado con anterioridad', 200);
            }
            return $this->buildExistResponse(true, 'El registro no existe, continue...', 200);
        }, self::LOG_CHANNEL, 'validarIdentificacionFleteExterno');
    }


    public function listaFletesExternosPorProveedorPuntoVenta(Request $request)
    {
        return $this->handleApiAction(function () use ($request) {
            $validate       = $request->validate([
                'prov_id'   => 'required|exists:gp_proveedors,id',
                'pv_id'     => 'required|exists:gp_punto_ventas,id'
            ]);
            $provId = data_get($validate,  'prov_id');
            $pvId   = data_get($validate, 'pv_id');
            $lista  = $this->externalFreightService->getExternalFreightsByProviderAndSalesPoint($provId, $pvId);
            return $this->successResponse('Información Obtenida correctamente.!', $lista);
        }, self::LOG_CHANNEL, 'listaFletesExternosPorProveedorPuntoVenta');
    }
}
