<?php

namespace App\Services\Venta\Contracts;

use App\Models\GpUsuario;

interface InventoryReservationServiceInterface
{
    // Define los métodos que debe implementar el servicio

    public function hasAnyReserveState(int $quotationId);
    public function saveQuotationHeaderReserved(array $reserva, GpUsuario $user);
    public function fetchListTipeReserved();

    public function fetchListReservedPending($perPage, $search, $sortBy, $sortOrder, $tipoReserva, $ptoVtaId, $clienteRuc);


    public function getQuotationWithDetails(int $id);
    public function getQuotationWithDetailsAndStock(int $id);
    public function getQuotationWithDetailsAndActiveStockById(int $id);
    public function denyApplicationReservedQuotation(array $data, $gpUser);
    public function approvalApplicationReservedQuotation(array $data, $gpUser);
    public function fetchListApprovedQuotation($perPage, $search, $sortBy, $sortOrder, $fecha_desde, $fecha_hasta, $estado, $ptoVtaId, $clienteRuc, $userId);

    public function processReservedQuotation(array $data, $user);
    public function processReservedQuotationInvoiceDemand($payload, $user);
    public function recalculateQuotation($quotationId);

    public function fetchListTypeReservations();


    /** proceso para lista de transferencias por reservas */

    public function fetchReservedTransferSummaries($ptoVtaId);
    public function fectchReservedTransferDetailsHeader($ptoVtaOrigen, $ptoVtaDestino): array;
    public function fetchReservedTransfersByDestinationWarehouses($ptoVtaOrigen, $ptoVtaDestino, $quotation = null): array;

    public function updatePickingItemsSelected($items): int;

    public function cancelPickingOneItemSelected($item): int;

    public function generatePickingPdf($payload);

    public function fetchVehicles();

    public function fetchPendingReservationdeliveryNotes($ptoVtaOrigen, $ptoVtaDestino);

    /**
     *
     * Proceso para recibir reservas
     *
     */

    public function paginateTransfersToReceive(string $ptoVta, int $perpage = 10, ?string $search = null, string $sortBy = 'created_at', string $sortOrder = 'desc');
    public function getTransferToReceiveDetails(int $id);

    public function printOrderStorageReceivedTranfered(array $payload);
    public function receiveItemsFromTransferred(int $guiaId, array $items);


    /** proceso de validacion y verificacion de stock disponible */

    public function validateAndProcessQuotationStock($quoteId);

}
