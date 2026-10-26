<?php

namespace App\Services\Ventas\Reservas\Builders\Contracts;

use App\Models\Ventas\Cotizaciones\GpCotizacionDetalle;
use App\Models\Admin\Usuarios\GpUsuario;
use App\Models\Ventas\Reservas\GpCotizacionReservaDetalleLog;
use App\Models\Ventas\Reservas\GpCotizacionReservaLog;
use App\Models\Ventas\Reservas\GpPedidoReservaDetalleLog;
use App\Models\Ventas\Reservas\GpPedidoReservaLog;
use App\Models\Ventas\Reservas\GpReserva;
use App\Models\Ventas\Reservas\GpTrasladosReservas;

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
