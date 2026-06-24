<?php

namespace App\Services\Builders\Bodega\Contracts;

use App\Models\despachos\GpGuiaRemision;
use App\Models\despachos\GpGuiaRemisionDetalle;
use App\Models\GpUsuario;

interface DeliveryNoteDispatchParamsBuilderInterface
{
    // Define los métodos que debe implementar el servicio

    public function buildDeliveryNoteDispatchHeader(array $payload, array $infoGuia): GpGuiaRemision;
    public function buildDeliveryNoteDispatchDetail(int $guiaId, array $detalle): GpGuiaRemisionDetalle;
    public function buildDeliveryNoteHeaderToGp(GpGuiaRemision $guia, ?string $ordenCompra = null): array;
    public function buildDeliveryNoteDestinationToGp(GpGuiaRemision $guia, ?string $ordenCompra = null): array;
    public function buildDeliveryNoteDetailToGp(GpGuiaRemision $header, GpGuiaRemisionDetalle $detail, int $indice): array;
    public function buildParameterHeaderAndDetailDispatchShipment(GpGuiaRemision $guia, string $documento, string $newOrder, array $items, GpUsuario $user, string $hashInterno): array;
    public function buildDeliveryNoteValidateDispatchShipment(array $params, string $tipoMovimiento, string $tipo): array;
}
