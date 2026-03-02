<?php

namespace App\Services\Builders\Bodega\Contracts;

use App\Models\despachos\GpGuiaRemision;
use App\Models\despachos\GpGuiaRemisionDetalle;

interface DeliveryNoteDispatchParamsBuilderInterface
{
    // Define los métodos que debe implementar el servicio

    public function buildDeliveryNoteDispatchHeader($payload, $infoGuia): GpGuiaRemision;
    public function buildDeliveryNoteDispatchDetail($guiaId, $detalle): GpGuiaRemisionDetalle;
    public function buildDeliveryNoteHeaderToGp($guia): array;
    public function buildDeliveryNoteDestinationToGp($guia): array;
    public function buildDeliveryNoteDetailToGp(GpGuiaRemision $header, GpGuiaRemisionDetalle $detail, int $indice): array;
    public function buildParameterHeaderAndDetailDispatchShipment(GpGuiaRemision $guia, string $documento, string $newOrder, array $items, $user, string $hashInterno): array;
    public function buildDeliveryNoteValidateDispatchShipment(array $params,  string $tipoMovimiento , string $tipo,): array;
}
