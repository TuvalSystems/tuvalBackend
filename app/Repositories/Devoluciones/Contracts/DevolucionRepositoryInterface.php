<?php

namespace App\Repositories\Devoluciones\Contracts;

use App\Models\Devoluciones\GpDevoluciones;
use App\Models\Ventas\Clientes\GpCliente;
use App\Models\Ventas\Pedidos\GpPedido;
use App\Models\Admin\PuntosVenta\GpPuntoVenta;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

interface DevolucionRepositoryInterface
{

    public function isInvoiceAlreadyVoided(GpPedido $pedido, GpDevoluciones $devolucion): bool;
    public function isOrderNotDispatchedOrProcessing(array|GpPedido $pedido): bool;
    public function hasOtherActiveReturns(array|GpPedido $pedido, array|GpDevoluciones $devolucion): bool;
    public function validateItemLDetailInvoice(array|GpDevoluciones $devolucion): bool;

    public function getDetailDevolucion(int|string $devolucionId, int|bool|null $despachadoParcial = null): array;

    public function hasPartiallyDispatchedItems(array|GpDevoluciones $devolucion): bool;

    public function getReturnsAnulated(int|string|null $pedidoId): bool;


    // funciones para el detallepedidodevolucion - getDetailOrderReturnById
    public function getReturnsInProcess(int|string|null $pedidoId): bool;

    public function getMotivosDevolucion(): EloquentCollection;

    public function getClientsByClientId(int|string|null $clienteId): GpCliente;

    public function getSumGuidesRemisionByOrderDetailId(int|string $detalleId): int|float;

    public function sumReturnedQtyByDetailId(int|string $detalleId, int|bool|null $despachoParcial): int|float;

    public function sumReturnedQtyByDetailIdDontDispatch(int|string $detalleId): int|float;

    public function getReturnsHistoryByOrderId(int|string|null $pedidoId): bool;

    // imprimir devolucion
    public function getReturnByIdToArray(int|string $devolucionId): array;
    public function getOrderByIdWithUsers(int|string $pedidoId): array;
    public function getByPtoVtaIdReturns(string $puntoVentaId): GpPuntoVenta;
    public function getClientsByClientIdToArray(int|string|null $clienteId): array;
    public function getSucursalByInvoiceToArray(int|string|null $invoiceId): array;
    public function getDetailDevolucionById(int|string $devolucionId): array;
    public function getDispatchOrder(int $orderId): bool;

     //refactura

    public function shouldMarkAsDispatched(int $devId): bool;
}
