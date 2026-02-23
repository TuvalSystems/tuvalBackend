<?php

namespace App\Services\Builders\Bodega\Contracts;

use App\Models\despachos\GpGuiaRemision;

interface DeliveryNoteSalesTransferBuilderInterface
{
    // Define los métodos que debe implementar el servicio
    public function buildDeliveryNoteSalesTransferHeader($payload, $infoGuia): GpGuiaRemision;
}
