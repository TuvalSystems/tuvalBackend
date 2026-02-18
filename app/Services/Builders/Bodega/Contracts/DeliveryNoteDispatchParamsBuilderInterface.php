<?php

namespace App\Services\Builders\Bodega\Contracts;

use App\Models\despachos\GpGuiaRemision;
use App\Models\despachos\GpGuiaRemisionDetalle;

interface DeliveryNoteDispatchParamsBuilderInterface
{
    // Define los métodos que debe implementar el servicio

      public function buildDeliveryNoteDispatchHeader($payload, $infoGuia): GpGuiaRemision;
    public function buildDeliveryNoteHeaderToGp($guia): array;
    public function buildDeliveryNoteDestinationToGp($guia): array;
    public function buildDeliveryNoteDetailToGp(GpGuiaRemision $header, GpGuiaRemisionDetalle $detail, int $indice): array;

}
