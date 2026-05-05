<?php

namespace App\Services\Devoluciones\Contracts;

use App\Models\GpUsuario;
use Illuminate\Pagination\LengthAwarePaginator;

interface DevolucionServiceInterface
{
    public function validateInvoiceNotDispatchOrInProcess($pedido);
    public function countsReturnsByOrders($pedido, $devolucion);

    public function handleDevolutionTransaction($data, GpUsuario $user);
    // public function anularDevolucion($pedido, $devolucion);

    public function canBeAnulledByLimitDate($pedido);

    // public function executeReturn($data, GpUsuario $user);

    public function validateItemLDetailInvoice($devolucion);

    public function getReturnsDetailDevolucion($devolucion);

    public function validateItemPartialDispatch($devolucion);

    public function getDetailOrderReturnById(array $order);

    public function printReturn($devolucion_id);
    public function printReturnOld($devolucion_id);


    public function fetchReturnsToReceived($perPage = 10, $search, $sortBy = 'gd.created_at', $sortOrder = 'desc', $ptoVtaId): LengthAwarePaginator;

    public function getReturnDetailToReceiveById(int $id);
    public function generatePdfOrderStorageDevolution(array $data);
    public function receivedDevolutions(array $header, array $detail, GpUsuario $user);
    public function dennyDevolucion(int $id, GpUsuario $user);

    /** funciones a ejecutar en caso de que no se genere la nota de credito o el ajuste  */
    public function generarNotaCreditoDevolucionesFallidas($id);
    public function generarAjusteDevolucionesVentaFallidas($id);

    public function responseCreditNoteDevolution($devolucionId);

}
