<?php

namespace App\Repositories\Venta\Contracts;

interface QuotationRepositoryInterface
{

    public function getScheduledReminders();

    public function getGpTempCartFillQuotation($sucursalId, $puntoVentaId, $clienteId, $userId);

}
