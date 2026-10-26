<?php

namespace App\Services\Inventario\Despachos\Contracts;

use Illuminate\Pagination\LengthAwarePaginator;

interface DispatchServiceInterface
{

    public function guiasDespachadas($ptoVtaId, $perpage, $search,  $sortBy, $sortOrder, $fecha_desde, $fecha_hasta, $clienteRuc);
    public function transferenciasEnviadas($ptoVtaId, $perpage, $search, $sortBy, $sortOrder, $fecha_desde, $fecha_hasta, $clienteRuc, $ptoVta);
    public function transferenciasRecibidas($ptoVtaId, $perpage, $search, $sortBy, $sortOrder, $fecha_desde, $fecha_hasta, $clienteRuc, $ptoVta);
    public function reposicionesEnviadas($ptoVtaId, $perpage, $search, $sortBy, $sortOrder, $fecha_desde, $fecha_hasta, $clienteRuc,  $ptoVta);
    public function reposicionesRecibidas($ptoVtaId, $perpage, $search, $sortBy, $sortOrder, $fecha_desde, $fecha_hasta, $clienteRuc, $ptoVta);
    public function fetchListSendReservations(int $perPage, string $search, ?string  $sortBy, string $sortOrder = 'desc', string  $ptoVtaOrigin,  ?string $fecha_desde,   ?string $fecha_hasta,  ?string $ptoVtaDestino = null);
    public function fecthListReceivedReservations(int $perPage, string $search, ?string  $sortBy, string $sortOrder = 'desc', string  $ptoVtaOrigin,  ?string $fecha_desde,   ?string $fecha_hasta,  ?string $ptoVtaDestino = null);
    public function checkTraslado($order);
    public function getAllPtoVta();
    public function getAllPtoVtaReserved();

     public function fetchListDispatchOrdersConsult(array $filters) : LengthAwarePaginator;
    /** PROCESO DE CANCELACION DE ORDEN DE DESPACHO */

    public function cancelDispatchIfEmptyPicking(int $orderId);
    public function printOrderDispatchPdf(int $id) : array;


    public function checkCompletedDispatch();
}
