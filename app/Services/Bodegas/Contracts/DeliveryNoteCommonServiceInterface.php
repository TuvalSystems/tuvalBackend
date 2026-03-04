<?php

namespace App\Services\Bodegas\Contracts;

use App\Models\despachos\GpGuiaRemision;
use App\Models\despachos\GpGuiaRemisionTransferencia;
use App\Models\GpUsuario;
use Illuminate\Support\Collection;

interface DeliveryNoteCommonServiceInterface
{
    // Define los métodos que debe implementar el servicio

    public function getNextGuideSequential(string $establecimiento, string $ptoEmision): string;
    public function groupItemsByOrderReservation(Collection $details): array;

    public function moveReservedToTransferredForTrfvLine($trl, $qty);

    public function getVehicleAndCarrierInfo($payload): array;

    public function getDirection($ptoWarehouse): string;
    public function getWarehouseDescriptionOrigin($ptoWarehouse);
    public function formatGuideNumber(string $ptoVta, string $ptoEmision, string $secuencia): string;

    public function assigmentAccessCodeToGuidePending($guia);

    /** funciones temporales para el bloque de guias */

    public function isGuideBlockedForOrder($guia): bool;
    public function createTemporaryDeliveryNoteBlocksOrder($guia, GpUsuario $user);

    public function getPtoVtaByWarehouse($bodega): ?string;
    public function checkMovementExistsWeb(int $guiaId, string  $order, string $docGp, string $hash, string $typeMov, string $type): bool;
    public function createMovementTransferSales(GpGuiaRemision $guia, string $order, array $data, string $hash, string $typeMov, string $type): GpGuiaRemisionTransferencia;

    public function validatePendingForReceivedSalesTransfer(GpGuiaRemision $guia): bool;

    public function generateDeterministicUuidDispatch(int $guiaId, string $pedido, array $items, Collection $articulos): string;


    //recibir reservas

    public function groupItemsByQuotesToReceivedReserved(array $items, Collection $movimientos, Collection $traslados): array;
}
