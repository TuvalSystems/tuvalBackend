<?php

namespace App\Services\Builders\Venta\Contracts;

use App\Models\GpUsuario;
use App\Models\reservas\GpGuiaRemisionReserva;
use Illuminate\Database\Eloquent\Collection;

interface ReservedBuilderInterface
{
    // Define los métodos que debe implementar el servicio
    public function fromPayloadReserved(array $data, GpUsuario $user);
    public function fromPayloadQuoteReservedLog($reserveId, $oldQuoteId, $newQuoteId, $userId, $bodega);
    public function fromPayloadQuoteReservedDetailLog($ctRsLogId, $stockId, $artCode, $warehouse, $qty);
    public function fromPayloadOrderReservedLog($reserveId, $oldQuoteId, $newQuoteId, $userId, $warehouse);
    public function formPayloadOrderReservedDetailLog($ctRsLogId, $stockId, $artCode, $warehouse, $qty);
    public function formPayloadTransferReserved($detail);


    /** builders para recibir la guia y enviar a gp */
    public function formPayloadReservationReceivedDetail(array $items, Collection $movimientos, Collection $traslados): array;
    public function buildReservationDeliveryNoteReceivedPayload(GpGuiaRemisionReserva $guia, string $documento, array $items, string $movimiento, string $user): array;
     public function buildRecievedDeliveryNoteValidationPayload(array $params): array;


     /** guardar en tablas de ingreso */

      public function createRegisterHistortyHeader($guia, GpUsuario $user);
       public function createRegisterHistoryDetail($items, $ing);
}
