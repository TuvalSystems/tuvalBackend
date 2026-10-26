<?php

namespace App\Services\Inventario\GuiasRemision\Contracts;


interface DeliveryNoteServiceInterface
{
    // Define los métodos que debe implementar el servicio
    public function getDeliveryNoteDetailByType(int|string $guiaId): array;
    public function mapAssigmentNumberGuideRemision();


    public function getDeliveryNoteAdjustmentInfoByDeliveryNoteId(int $id);

    public function processAjusteWevOrderGUideRemision(int $id);

    public function getDeliveryNoteDispatchWithAjustment(string $fechaInicio, string $fechaFin);
    public function getDeliveryNoteTransferAndRepositionAjustment(string $fechaInicio, string $fechaFin, $estado = null);

    public function consultExistenceGeneratedGuides(array $payload): string;
}
