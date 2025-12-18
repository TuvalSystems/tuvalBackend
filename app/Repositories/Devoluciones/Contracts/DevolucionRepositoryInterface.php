<?php

namespace App\Repositories\Devoluciones\Contracts;


interface DevolucionRepositoryInterface
{

    public function isInvoiceAlreadyVoided($pedido, $devolucion);
    public function isOrderNotDispatchedOrProcessing($pedido);
    public function hasOtherActiveReturns($pedido, $devolucion) :bool;
    public function validateItemLDetailInvoice($devolucion) : bool;

    public function getOrderbyId($pedido_id);
    public function getDetailDevolucion($devolucion_id, $despachadoParcial = null);

    public function hasPartiallyDispatchedItems($devolucion);

    public function getReturnsAnulated($pedido_id) : bool;


    // funciones para el detallepedidodevolucion - getDetailOrderReturnById
    public function getReturnsInProcess($pedido_id) : bool;

    public function getMotivosDevolucion();

    public function getClientsByClientId($cliente_id);

    public function getClientsByClientRuc($ruc);

    public function getClientsSucursByClientRuc($ruc);

    public function getSumGuidesRemisionByOrderDetailId($detalle_id);

    public function sumReturnedQtyByDetailId($detalle_id, $despacho_parcial);

    public function sumReturnedQtyByDetailIdDontDispatch($detalle_id);

    public function getReturnsHistoryByOrderId($pedido_id);

    // imprimir devolucion
    public function getReturnByIdToArray($devolucion_id);
    public function  getOrderByIdWithUsers($pedido_id);
    public function getByPtoVtaIdReturns($punto_venta_id);
    public function getClientsByClientIdToArray($cliente_id);
    public function getSucursalByInvoiceToArray($invoice_id);
    public function getDetailDevolucionById($devolucion_id);
}
