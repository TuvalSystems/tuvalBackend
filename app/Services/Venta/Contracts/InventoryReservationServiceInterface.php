<?php

namespace App\Services\Venta\Contracts;

use App\Models\GpUsuario;

interface InventoryReservationServiceInterface
{
    // Define los métodos que debe implementar el servicio

    public function hasAnyReserveState(int $quotationId);
    public function saveQuotationHeaderReserved(array $reserva,  GpUsuario $user);
    public function fetchListTipeReserved();

    public function fetchListReservedPending($perPage, $search, $sortBy, $sortOrder, $tipoReserva, $ptoVtaId, $clienteRuc);


    public function getQuotationWithDetails(int $id);
    public function getQuotationWithDetailsAndStock(int $id);
    public function getQuotationWithDetailsAndActiveStockById(int $id);
    public function denyApplicationReservedQuotation(array $data, $gpUser);
    public function approvalApplicationReservedQuotation(array $data, $gpUser);
    public function fetchListApprovedQuotation($perPage, $search, $sortBy, $sortOrder, $fecha_desde, $fecha_hasta, $estado, $ptoVtaId,  $clienteRuc, $userId);

    public function processReservedQuotation(array $data, $user);
    public function processReservedQuotationInvoiceDemand($payload, $user);
    public function recalculateQuotation($quotationId);

    public function fetchListTypeReservations();


    /** proceso para lista de transferencias por reservas */

    public function fetchReservedTransferSummaries($ptoVtaId);
    public function fetchReservedTransfersByDestinationWarehouses($ptoVtaOringen, $ptoVtaDestino): array;
}
