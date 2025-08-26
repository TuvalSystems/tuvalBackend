<?php

namespace App\Services\Bodegas\Contracts;

use App\Models\despachos\GpGuiaRemision;

interface DeliveryNoteServiceInterface
{
    // Define los métodos que debe implementar el servicio
    public function getDeliveryNoteDetailByType(int|string $guiaId): array;


    // proceso de despacho de guia
    public function dispatchGuideTransferShipment(GpGuiaRemision $guia, array $detalle, $user);
    //despacho
    public function dispatchGuideDispatchShipment(GpGuiaRemision $guia, array $detalle, $user);
    // reposicion
    public function dispatchGuideReplacementShipment(GpGuiaRemision $guia, array $detalle, $user);
}
