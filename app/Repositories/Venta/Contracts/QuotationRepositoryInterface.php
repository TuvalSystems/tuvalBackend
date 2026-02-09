<?php

namespace App\Repositories\Venta\Contracts;

interface QuotationRepositoryInterface
{

    public function getScheduledReminders();

    public function getGpTempCartFillQuotation($sucursalId, $puntoVentaId, $clienteId, $userId);
    public function getQuotationHeaderById(int $id);

    /** funciones para cart temp */
    public function computedMaxOrder(int $clientId, string $ptoVta, int $sucursalId);
    public function deleteProviderRegisterTemp($tempId);
    public function updateTempCartArtValor($idTempCart, $artValor);

}
