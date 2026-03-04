<?php

namespace App\Services\Bodegas\Contracts;

use App\Models\despachos\GpGuiaRemision;
use Illuminate\Support\Collection;

interface DeliveryNoteServiceInterface
{
    // Define los métodos que debe implementar el servicio
    public function getDeliveryNoteDetailByType(int|string $guiaId): array;
    public function mapAssigmentNumberGuideRemision();
    // proceso de despacho de guia
    // public function dispatchGuideTransferShipment(GpGuiaRemision $guia, Collection $detalle, $user);
    // reposicion
    public function dispatchGuideReplacementShipment(GpGuiaRemision $guia, Collection $detalle, $user);

    public function getDeliveryNoteAdjustmentInfoByDeliveryNoteId(int $id);

    public function processAjusteWevOrderGUideRemision($id);

    public function getDeliveryNoteDispatchWithAjustment($fechaInicio, $fechaFin);
    public function getDeliveryNoteTransferAndRepositionAjustment($fechaInicio, $fechaFin, $estado = null);

    public function consultExistenceGeneratedGuides(array $payload): string;
}
