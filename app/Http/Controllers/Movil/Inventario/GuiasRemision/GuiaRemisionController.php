<?php

namespace App\Http\Controllers\Movil\Inventario\GuiasRemision;

use App\Http\Controllers\Controller;
use App\Services\Inventario\GuiasRemision\Contracts\ReceivedDeliveryNoteServiceInterface;
use App\Traits\ExecuteApiTransactions;
use App\Traits\JsonResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class GuiaRemisionController extends Controller
{
    use JsonResponseTrait, ExecuteApiTransactions;
    private const LOG_CHANNEL = 'stack';

    public function __construct(
        protected readonly ReceivedDeliveryNoteServiceInterface $guides
    ) {}

    public function tipos(): JsonResponse
    {
        return $this->handleApiAction(function (): JsonResponse {
            $data = $this->guides->types();
            return $this->successResponse('Tipos de guías obtenidos.', $data, 200);
        }, self::LOG_CHANNEL, 'GuiaRemisionController@tipos');
    }

    public function obtenerBodegas(): JsonResponse
    {
        return $this->handleApiAction(function (): JsonResponse {
            $data = $this->guides->getWarehouses();
            return $this->successResponse('Bodegas obtenidas correctamente', $data, 200);
        }, self::LOG_CHANNEL, 'GuiaRemisionController@obtenerBodegas');
    }

    public function index(Request $request): JsonResponse
    {
        return $this->handleApiAction(function () use ($request): JsonResponse {
            $filters = $request->validate([
                'tipo'              => ['required', Rule::in(array_column($this->guides->types(), 'id'))],
                'punto_venta_id'    => ['required', 'integer', 'exists:gp_punto_ventas,id'],
                'bodega_origen_id' => ['nullable', 'prohibited_if:tipo,despacho', 'integer', 'exists:gp_bodegas,id'],
                'cliente_id'        => ['nullable', 'prohibited_unless:tipo,despacho', 'integer', 'exists:gp_clientes,id'],
                'page'              => ['sometimes', 'integer', 'min:1'],
                'per_page'          => ['sometimes', 'integer', 'min:1', 'max:100'],
            ]);

            $user = $request->user();
            $data = $this->guides->listReceived($filters, (int) $user->id);
            return $this->successResponse('Guías obtenidas.', $data, 200);
        }, self::LOG_CHANNEL, 'GuiaRemisionController@index');
    }
}
