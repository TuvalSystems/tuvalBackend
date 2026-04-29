<?php

namespace App\Services\Bodegas\Contracts;

use App\Models\GpUsuario;

interface TransferServiceInterface
{
    // Define los métodos que debe implementar el servicio


    public function fetchSalesTransfersSunnaries(string $ptoVtaId);
    public function getSalesTransferHeadersBetweenWarehouses(string $origen, string $destino);
    public function getListSalesTransferDetailsBetweenWarehouses(string $origen, string $destino, ?string $pedido = null, ?string $bin = null);
    public function updatePickingSalesTransfers(array $payload);
    public function printPickingSalesTransfers(array $payload);
    public function checkOrderTranfer();
    public function cancelPicking(array $payload, GpUsuario $user);
     public function fetchPendingSalesTransferDeliveryNotes(string $ptoVtaOrigen, string $ptoVtaDestino);
}
