<?php

namespace App\Services\Builders\Venta;

use App\Models\GpUsuario;
use App\Models\reservas\GpCotizacionReservaDetalleLog;
use App\Models\reservas\GpCotizacionReservaLog;
use App\Models\reservas\GpPedidoReservaDetalleLog;
use App\Models\reservas\GpPedidoReservaLog;
use App\Models\reservas\GpReserva;
use App\Services\Builders\Venta\Contracts\ReservedBuilderInterface;
use Carbon\Carbon;

class ReservedBuilder implements ReservedBuilderInterface
{

    public function __construct()
    {
        // Inyecta dependencias si es necesario
    }


    public function fromPayloadReserved(array $data,  GpUsuario $user)
    {
        $new = new GpReserva();
        $new->fill([
            'cotizacion_id' => $data['cotizacion_id'],
            'estado' => 'pendiente',
            'user_ingreso_reserva_id' => $user->id,
            'tipo_reserva_id' => $data['tipo_reserva_id'],
            'tipo_traslado_reserva_id' => $data['tipo_traslado_reserva_id'],
            'fe_solicitud_reserva' => $data['fe_solicitud_reserva'],
            'fe_ingreso_solicitud_reserva' => Carbon::now(),
            'obs_solicitud_reserva' => $data['obs_solicitud_reserva'],
            'fe_autorizacion_reserva' => $data['fe_solicitud_reserva'],
        ]);
        return $new;
    }



    public function fromPayloadQuoteReservedLog($reserveId, $oldQuoteId, $newQuoteId, $userId ,$warehouse){
        $new = new GpCotizacionReservaLog();
        $new->fill([
            'reserva_id' => $reserveId,
            'cotizacion_origen_id' => $oldQuoteId,
            'cotizacion_destino_id' => $newQuoteId,
            'bodega' => $warehouse,
            'user_id' => $userId,
        ]);
        return $new;
    }


    public function fromPayloadQuoteReservedDetailLog($ctRsLogId, $stockId, $artCode, $warehouse, $qty){
        $new  = new GpCotizacionReservaDetalleLog();

        $new->fill([
            'ct_reserva_id' => $ctRsLogId,
            'stock_id' => $stockId,
            'art_code' => $artCode,
            'bodega' => $warehouse,
            'cantidad' => $qty
        ]);
        return $new;
    }


    public function fromPayloadOrderReservedLog($reserveId, $oldQuoteId, $newQuoteId, $userId ,$warehouse){
        $new = new GpPedidoReservaLog();
        $new->fill([
            'reserva_id' => $reserveId,
            'cotizacion_origen_id' => $oldQuoteId,
            'pedido_destino_id' => $newQuoteId,
            'user_id' => $userId,
            'bodega' => $warehouse,
        ]);
        return $new;
    }

    public function formPayloadOrderReservedDetailLog($ctRsLogId, $stockId, $artCode, $warehouse, $qty){
        $new  = new GpPedidoReservaDetalleLog();
        $new->fill([
            'pedido_reserva_id' => $ctRsLogId,
            'stock_id' => $stockId,
            'art_code' => $artCode,
            'bodega' => $warehouse,
            'cantidad' => $qty
        ]);
        return $new;
    }

}
