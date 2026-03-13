<?php

namespace App\Http\Controllers\test;

use App\Http\Controllers\Controller;
use App\Models\GpPedido;
use App\Services\Venta\Contracts\PurchaseOrderServiceInterface;
use App\Traits\ExecuteApiTransactions;
use App\Traits\JsonResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TestController extends Controller
{
    use JsonResponseTrait, ExecuteApiTransactions;
    public function __construct(protected readonly PurchaseOrderServiceInterface $purchase) {}


    public function crearOrdenCompraItemsL(int $id): JsonResponse
    {
        return $this->executeTransaction(function () use ($id) {
            $pedido = GpPedido::findOrFail($id);
            DB::transaction(fn() => $this->purchase->checkPurchaseOrdersV2($pedido));
            return $this->successResponse('Información obtenida', $pedido, 200);
        }, "invoice", "crearOrdenCompraItemsL Error creando OC para pedido_id {$id}");
    }


    public function enviarOrdenesCompraLIniciadas(): JsonResponse
    {
        try {
            $ordenes = $this->purchase->sendPurchaseOrdersV2();
            return $this->successResponse('Información obtenida', $ordenes, 200);
        } catch (\Throwable $th) {
            return $this->buildErrorResponse($th->getMessage(), 500);
        }
    }
}
