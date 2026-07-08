<?php

namespace App\Services\Venta\Contracts;

use App\Models\GpUsuario;
use App\Models\Reservas\GpReserva;
use Illuminate\Database\Eloquent\Builder;

interface InventoryReservationServiceInterface
{
    // Define los métodos que debe implementar el servicio

    public function hasAnyReserveState(int $quotationId);
    public function saveQuotationHeaderReserved(array $reserva, GpUsuario $user);
    public function fetchListTipeReserved();

    public function fetchListReservedPending($perPage, $search, $sortBy, $sortOrder, $tipoReserva, $ptoVtaId, $clienteRuc);
    public function cancelReservedQuotation(int $id): Builder|GpReserva;

    public function getQuotationWithDetails(int $id);
    public function getQuotationWithDetailsAndStock(int $id);
    public function getQuotationWithDetailsAndActiveStockById(int $id);
    public function denyApplicationReservedQuotation(array $data, GpUsuario $gpUser);
    public function approvalApplicationReservedQuotation(array $data, GpUsuario $gpUser);
    public function fetchListApprovedQuotation($perPage, $search, $sortBy, $sortOrder, $fecha_desde, $fecha_hasta, $estado, $ptoVtaId, $clienteRuc, GpUsuario $user);

    public function processReservedQuotation(array $data, $user);
    public function processReservedQuotationInvoiceDemand( $payload, GpUsuario $user): array ;

    public function fetchListTypeReservations();


    /** proceso para lista de transferencias por reservas */

    public function fetchReservedTransferSummaries(string $ptoVtaId);
    public function fectchReservedTransferDetailsHeader(string $ptoVtaOrigen, string $ptoVtaDestino): array;
    public function fetchReservedTransfersByDestinationWarehouses(string $ptoVtaOrigen, string $ptoVtaDestino, ?string $quotation = null): array;

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


    /** proceso de validacion y verificacion de stock disponible */

    public function validateAndProcessQuotationStock(int $quoteId);
}
