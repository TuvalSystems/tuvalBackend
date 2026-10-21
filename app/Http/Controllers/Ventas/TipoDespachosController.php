<?php

namespace App\Http\Controllers\Ventas;

use App\Enums\Ventas\DispatchTypeEnum;
use App\Http\Controllers\Controller;
use App\Services\Venta\TipoDespachos\Contracts\DispatchTypeServiceInterface;
use App\Traits\ExecuteApiTransactions;
use App\Traits\JsonResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rules\Enum;

class TipoDespachosController extends Controller
{
    use JsonResponseTrait, ExecuteApiTransactions;
    private const LOG_CHANNEL = 'cotizacion';
    public function __construct(
        protected readonly DispatchTypeServiceInterface $dispatchType
    ) {}


    public function listaTipoDespacho(Request $request): JsonResponse
    {
        return $this->handleApiAction(function () use ($request): JsonResponse {
            $filters = data_get($request, 'filters');
            $user = $request->user();
            $lista = $this->dispatchType->list($user, $filters);
            return $this->successResponse('Lista Tipo Despacho Obtenida Correctamente', $lista);
        }, self::LOG_CHANNEL, 'listaTipoDespacho');
    }

    public function crearTipoDespacho(Request $request): JsonResponse
    {
        return $this->handleApiAction(function () use ($request): JsonResponse {
            $info = data_get($request, 'payload');
            $user = $request->user();
            $this->dispatchType->create($info, $user);
            return $this->buildSuccessResponse("Registro creado correctamente");
        }, self::LOG_CHANNEL, 'crearTipoDespachp');
    }

    public function obtenerDetalleTipoDespacho(Request $request): JsonResponse
    {
        return $this->handleApiAction(function () use ($request): JsonResponse {
            $validate = $request->validate([
                'id' => 'required|integer|exists:tipo_despachos,id',
            ]);
            $id = data_get($validate, 'id');
            $info = $this->dispatchType->getDetail($id);
            return $this->successResponse('Información Obtenida Correctamente', $info);
        }, self::LOG_CHANNEL, 'obtenerDetalleTipoDespacho');
    }

    public function actualizarTipoDespacho(int $id, Request $request): JsonResponse
    {
        return $this->handleApiAction(function () use ($id, $request): JsonResponse {
            $info = data_get($request, 'payload');
            $user = $request->user();
            $this->dispatchType->update($id, $info, $user);
            return $this->buildSuccessResponse('Registro actualizado correctamente');
        }, self::LOG_CHANNEL, 'actualizarTipoDespacho');
    }

    public function actualizarEstadoTipoDespacho(Request $request): JsonResponse
    {
        return $this->handleApiAction(function () use ($request) {
            $validate = $request->validate([
                'id' => 'required|integer|exists:tipo_despachos,id',
            ]);
            $id = data_get($validate, 'id');
            $this->dispatchType->activeOrInactive($id);
            return $this->buildSuccessResponse('Estado Actualizado Correctamente');
        }, self::LOG_CHANNEL, 'actualizarEstadoTipoDespacho');
    }

    public function obtenerListaPuntoVentaActivos(): JsonResponse
    {
        return $this->handleApiAction(function () {
            $lista = $this->dispatchType->getActivePointSales();
            return $this->successResponse('Información Obtenida correctamente', $lista);
        }, self::LOG_CHANNEL, 'obtenerListaPuntoVentaActivos');
    }

    public function obtenerTipoDespachoPorResponsable(Request $request): JsonResponse
    {
        return $this->handleApiAction(function () use ($request): JsonResponse {
            $validated = $request->validate([
                'tipo'  => ['required', 'string', new Enum(DispatchTypeEnum::class)],
                'pv_id' => ['required', 'exists:gp_punto_ventas,id']
            ]);
            $paymentResponsible = data_get($validated,'tipo');
            $pvId  = data_get($validated, 'pv_id');
            $lista = $this->dispatchType->getDispatchTypesByPaymentResponsible($pvId, $paymentResponsible);
            return $this->successResponse('Información obtenida correctamente', $lista);
        }, self::LOG_CHANNEL, 'obtenerTipoDespachoPorResponsable');
    }
}
