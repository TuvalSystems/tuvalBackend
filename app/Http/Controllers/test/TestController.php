<?php

namespace App\Http\Controllers\test;

use App\Http\Controllers\Controller;
use App\Models\GpPedido;
use App\Services\Funciones\Contracts\InvoiceServiceInterface;
use App\Services\Venta\Contracts\PurchaseOrderServiceInterface;
use App\Traits\ExecuteApiTransactions;
use App\Traits\JsonResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class TestController extends Controller
{
    use JsonResponseTrait, ExecuteApiTransactions;
    public function __construct(
        protected readonly PurchaseOrderServiceInterface $purchase,
        protected readonly InvoiceServiceInterface $invoice
    ) {}


    public function crearOrdenCompraItemsL(int $id): JsonResponse
    {
        return $this->handleApiAction(function () use ($id) {
            $pedido = GpPedido::find($id);

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



    public function generarOrdenCompraPedido(string $pedido)
    {
        return $this->handleApiAction(function () use ($pedido) {
            $pedido = GpPedido::where('pedido', $pedido)->first();
            DB::transaction(fn() => $this->invoice->processPurchaseOrders($pedido));
            return $this->successResponse('Información obtenida', $pedido, 200);
        }, 'invoice', 'generarOrdenCompraPedido');
    }
}
