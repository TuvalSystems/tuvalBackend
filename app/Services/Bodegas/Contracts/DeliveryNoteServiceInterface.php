<?php

namespace App\Services\Bodegas\Contracts;


interface DeliveryNoteServiceInterface
{
    // Define los métodos que debe implementar el servicio
    public function getDeliveryNoteDetailByType(int|string $guiaId): array;
    public function mapAssigmentNumberGuideRemision();


    public function getDeliveryNoteAdjustmentInfoByDeliveryNoteId(int $id);

    public function processAjusteWevOrderGUideRemision($id);

    public function getDeliveryNoteDispatchWithAjustment($fechaInicio, $fechaFin);
    public function getDeliveryNoteTransferAndRepositionAjustment($fechaInicio, $fechaFin, $estado = null);

    public function consultExistenceGeneratedGuides(array $payload): string;
}
