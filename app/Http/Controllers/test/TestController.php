<?php

namespace App\Http\Controllers\test;

use App\Http\Controllers\Controller;
use App\Models\GpPedido;
use App\Services\Venta\Contracts\PurchaseOrderServiceInterface;
use App\Traits\JsonResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TestController extends Controller
{
    use JsonResponseTrait;
    public function __construct(protected readonly PurchaseOrderServiceInterface $purchase) {}


    public function crearOrdenCompraItemsL(int $id): JsonResponse
    {
        try {
            $pedido = GpPedido::findOrFail($id);
            DB::transaction(fn() => $this->purchase->checkPurchaseOrdersV2($pedido));
            $pedido->refresh();
            return $this->successResponse('Información obtenida', $pedido, 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            // Catch específico para 404
            return $this->errorResponse("El pedido #$id no existe.", [], 404);
        } catch (\Throwable $e) {
            // 4. Loguear el error real para ti (desarrollador)
            Log::error("Error creando OC para pedido $id: " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);

            // Responder genérico al usuario (o detallado solo si es local)
            $msg = app()->environment('local') ? $e->getMessage() : 'Ocurrió un error interno al procesar la orden.';

            return $this->errorResponse("Opps! $msg", [], 500);
        }
    }
}
