<?php

namespace App\Services\Builders\Bodega\Contracts;

use App\Models\despachos\GpGuiaRemision;
use App\Models\GpUsuario;

interface DeliveryNoteParamsBuilderInterface
{
    // Define los métodos que debe implementar el servicio

    public function buildParamsHeaderTransfer(GpGuiaRemision $guia, string $documento, array $items, GpUsuario $user): array;
    public function buildParameterHeaderAndDetailDispatchShipment(GpGuiaRemision $guia, string $documento, string $newOrder, array $items, $user): array;



    public function buildingTransferGuideAdjustmentPayload($params);
    public function buildingRepositionGuideAdjustmentPayload($params);
}
