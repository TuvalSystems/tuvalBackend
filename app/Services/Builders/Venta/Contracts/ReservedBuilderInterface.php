<?php

namespace App\Services\Builders\Venta\Contracts;

use App\Models\GpUsuario;

interface ReservedBuilderInterface
{
    // Define los métodos que debe implementar el servicio
    public function fromPayloadReserved(array $data, GpUsuario $user);
    public function fromPayloadQuoteReservedLog($reserveId, $oldQuoteId, $newQuoteId, $userId, $bodega);
    public function fromPayloadQuoteReservedDetailLog($ctRsLogId, $stockId, $artCode, $warehouse, $qty);
    public function fromPayloadOrderReservedLog($reserveId, $oldQuoteId, $newQuoteId, $userId, $warehouse);
    public function formPayloadOrderReservedDetailLog($ctRsLogId, $stockId, $artCode, $warehouse, $qty);
    public function formPayloadTransferReserved($detail);

}
