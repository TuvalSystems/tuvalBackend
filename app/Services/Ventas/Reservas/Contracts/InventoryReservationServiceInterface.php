<?php

namespace App\Services\Ventas\Reservas\Contracts;

use App\Models\Ventas\Cotizaciones\GpCotizacion;
use App\Models\Admin\Usuarios\GpUsuario;
use App\Models\Ventas\Reservas\GpReserva;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface InventoryReservationServiceInterface
{
    // Define los métodos que debe implementar el servicio

    public function hasAnyReserveState(int $quotationId): bool;
    public function saveQuotationHeaderReserved(array $data, GpUsuario $user): GpCotizacion;
    public function fetchListTipeReserved(): EloquentCollection;

    public function fetchListReservedPending(int $perPage, ?string $search = null, ?string $sortBy = 'gp_reservas.created_at', ?string $sortOrder = 'desc', int|string|null $tipoReserva = null, int|string|null $ptoVtaId = null, ?string $clienteRuc = null): LengthAwarePaginator;
    public function cancelReservedQuotation(int $id): GpReserva;

    public function getQuotationWithDetails(int $id): array;
    public function getQuotationWithDetailsAndStock(int $id): array;
    public function getQuotationWithDetailsAndActiveStockById(int $id): array;
    public function denyApplicationReservedQuotation(array $data, GpUsuario $gpUser): GpReserva;
    public function approvalApplicationReservedQuotation(array $data, GpUsuario $gpUser): array;
    public function fetchListApprovedQuotation(int $perPage, ?string $search, ?string $sortBy, ?string $sortOrder, ?string $fecha_desde, ?string $fecha_hasta, ?string $estado, int|string|null $ptoVtaId, ?string $clienteRuc, GpUsuario $gpUser): LengthAwarePaginator;

    public function processReservedQuotation(array $payload, GpUsuario $user): array;
    public function processReservedQuotationInvoiceDemand(array $payload, GpUsuario $user): array;

    public function fetchListTypeReservations(): EloquentCollection;


    /** proceso para lista de transferencias por reservas */

    public function fetchReservedTransferSummaries(string $ptoVtaId): array;
    public function fectchReservedTransferDetailsHeader(string $ptoVtaOrigen, string $ptoVtaDestino): array;
    public function fetchReservedTransfersByDestinationWarehouses(string $ptoVtaOrigen, string $ptoVtaDestino, ?string $quotation = null): array;

    public function updatePickingItemsSelected(array|Collection|null $items): int;

    public function cancelPickingOneItemSelected(?array $item): int;

    public function generatePickingPdf(array $payload): array;

    public function fetchVehicles(): array;

    public function fetchPendingReservationdeliveryNotes(?string $ptoVtaOrigen, ?string $ptoVtaDestino): array|EloquentCollection;

    /**
     *
     * Proceso para recibir reservas
     *
     */

    public function paginateTransfersToReceive(string $ptoVta, int $perpage = 10, ?string $search = null, ?string $sortBy = null, string $sortOrder = 'desc'): LengthAwarePaginator;
    public function getTransferToReceiveDetails(int $id): Collection;

    public function printOrderStorageReceivedTranfered(array $payload): array;


    /** proceso de validacion y verificacion de stock disponible */

    public function validateAndProcessQuotationStock(int $quoteId): ?string;
}
