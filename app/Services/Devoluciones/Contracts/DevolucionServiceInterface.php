<?php

namespace App\Services\Devoluciones\Contracts;

use App\Models\GpUsuario;
use Illuminate\Pagination\LengthAwarePaginator;

interface DevolucionServiceInterface
{

    public function handleDevolutionTransaction(array $data, GpUsuario $user);


    public function getReturnsDetailDevolucion(array $devolucion);


    public function getDetailOrderReturnById(array $order);

    public function printReturn(int $devolucion_id);
    public function printReturnOld(int $devolucion_id);


    public function fetchReturnsToReceived($perPage = 10, $search, $sortBy = 'gd.created_at', $sortOrder = 'desc', $ptoVtaId): LengthAwarePaginator;

    public function getReturnDetailToReceiveById(int $id);
    public function generatePdfOrderStorageDevolution(array $data);
    public function receivedDevolutions(array $header, array $detail, GpUsuario $user);
    public function dennyDevolucion(int $id, GpUsuario $user);

    /** funciones a ejecutar en caso de que no se genere la nota de credito o el ajuste  */
    public function generarNotaCreditoDevolucionesFallidas(int $id);
    public function generarAjusteDevolucionesVentaFallidas(int $id);

    public function responseCreditNoteDevolution(int $devolucionId);

}
