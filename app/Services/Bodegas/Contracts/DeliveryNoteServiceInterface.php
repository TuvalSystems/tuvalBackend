<?php

namespace App\Services\Bodegas\Contracts;

use App\Models\despachos\GpGuiaRemision;
use Illuminate\Support\Collection;

interface DeliveryNoteServiceInterface
{
    // Define los métodos que debe implementar el servicio
    public function getDeliveryNoteDetailByType(int|string $guiaId): array;
    // public function asigmentNumberGuideRemision();
    public function mapAssigmentNumberGuideRemision();
    // proceso de despacho de guia
    public function dispatchGuideTransferShipment(GpGuiaRemision $guia, Collection $detalle, $user);
    //despacho
    public function dispatchGuideDispatchShipment(GpGuiaRemision $guia, Collection $detalle, $user);
    // reposicion
    public function dispatchGuideReplacementShipment(GpGuiaRemision $guia, Collection $detalle, $user);



    public function reassignDispatchGuideAdjustment($id);
    public function createAdjustmentDeliveryNote($id);

    public function getDeliveryNoteAdjustmentInfoByDeliveryNoteId(int $id);

     public function processAjusteWevOrderGUideRemision($id);

      public function getDeliveryNoteDispatchWithAjustment();
      public function getDeliveryNoteTransferAndRepositionAjustment();
}
