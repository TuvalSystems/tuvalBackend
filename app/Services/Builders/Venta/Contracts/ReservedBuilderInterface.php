<?php

namespace App\Services\Builders\Venta\Contracts;

use App\Models\GpCotizacionDetalle;
use App\Models\GpUsuario;
use App\Models\Reservas\GpCotizacionReservaDetalleLog;
use App\Models\Reservas\GpCotizacionReservaLog;
use App\Models\Reservas\GpPedidoReservaDetalleLog;
use App\Models\Reservas\GpPedidoReservaLog;
use App\Models\Reservas\GpReserva;
use App\Models\Reservas\GpTrasladosReservas;

interface ReservedBuilderInterface
{
    // Define los métodos que debe implementar el servicio
    public function fromPayloadReserved(array $data, GpUsuario $user): GpReserva;
    public function fromPayloadQuoteReservedLog(int $reserveId, int $oldQuoteId, int $newQuoteId, int $userId, string $warehouse): GpCotizacionReservaLog;
    public function fromPayloadQuoteReservedDetailLog(int $ctRsLogId, ?int $stockId, string $artCode, string $warehouse, float $qty): GpCotizacionReservaDetalleLog;
    public function fromPayloadOrderReservedLog(int $reserveId, int $oldQuoteId, int $newQuoteId, int $userId, string $warehouse): GpPedidoReservaLog;
    public function formPayloadOrderReservedDetailLog(int $ctRsLogId, ?int $stockId, string $artCode, string $warehouse, float $qty): GpPedidoReservaDetalleLog;
    public function formPayloadTransferReserved(GpCotizacionDetalle $detail): GpTrasladosReservas;

}
