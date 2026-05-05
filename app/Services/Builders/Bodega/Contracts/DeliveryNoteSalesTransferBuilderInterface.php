<?php

namespace App\Services\Builders\Bodega\Contracts;

use App\Models\despachos\GpGuiaRemision;
use App\Models\GpIngreso;
use App\Models\GpIngresosDetalle;
use App\Models\GpUsuario;

interface DeliveryNoteSalesTransferBuilderInterface
{
    // Define los métodos que debe implementar el servicio
    public function buildDeliveryNoteSalesTransferHeader(array $payload, array $infoGuia, array  $dataVehicle): GpGuiaRemision;
    public function buildDeliveryNoteReplenishmentTransferHeader(array $payload, array $infoGuia): GpGuiaRemision;

    public function buildDeliveryNoteReceivedPayload(GpGuiaRemision $guia, string $documento, array $items, string $user, string $hash ,?string $movimiento): array;


    public function buildDeliveryNoteValidateReceivedSalesTransfer(array $params, string $tipoMovimiento, string $tipo): array;

    public function createRegisterHistoryHeader(GpGuiaRemision $guia, GpUsuario $user, $document, string $type): GpIngreso;
    public function ceateRegisterHistoryDetail($item, $ing): GpIngresosDetalle;
}
