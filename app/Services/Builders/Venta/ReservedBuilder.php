<?php

namespace App\Services\Builders\Venta;

use App\Models\GpCotizacionDetalle;
use App\Models\GpUsuario;
use App\Models\Reservas\GpCotizacionReservaDetalleLog;
use App\Models\Reservas\GpCotizacionReservaLog;
use App\Models\Reservas\GpPedidoReservaDetalleLog;
use App\Models\Reservas\GpPedidoReservaLog;
use App\Models\Reservas\GpReserva;
use App\Models\Reservas\GpTrasladosReservas;
use App\Services\Builders\Venta\Contracts\ReservedBuilderInterface;
use Carbon\Carbon;


class ReservedBuilder implements ReservedBuilderInterface
{

    public function __construct()
    {
        // Inyecta dependencias si es necesario
    }


    public function fromPayloadReserved(array $data, GpUsuario $user): GpReserva
    {
        $new = new GpReserva();
        $new->fill([
            'cotizacion_id'                 => $data['cotizacion_id'],
            'estado'                        => 'pendiente',
            'user_ingreso_reserva_id'       => $user->id,
            'tipo_reserva_id'               => $data['tipo_reserva_id'],
            'tipo_traslado_reserva_id'      => $data['tipo_traslado_reserva_id'],
            'fe_solicitud_reserva'          => $data['fe_solicitud_reserva'],
            'fe_ingreso_solicitud_reserva'  => Carbon::now(),
            'obs_solicitud_reserva'         => $data['obs_solicitud_reserva'],
            'fe_autorizacion_reserva'       => $data['fe_solicitud_reserva'],
        ]);
        return $new;
    }



    public function fromPayloadQuoteReservedLog(int $reserveId, int $oldQuoteId, int $newQuoteId, int $userId, string $warehouse): GpCotizacionReservaLog
    {
        $new = new GpCotizacionReservaLog();
        $new->fill([
            'reserva_id'            => $reserveId,
            'cotizacion_origen_id'  => $oldQuoteId,
            'cotizacion_destino_id' => $newQuoteId,
            'bodega'                => $warehouse,
            'user_id'               => $userId,
        ]);
        return $new;
    }


    public function fromPayloadQuoteReservedDetailLog(int $ctRsLogId, ?int $stockId, string $artCode, string $warehouse, float $qty): GpCotizacionReservaDetalleLog
    {
        $new = new GpCotizacionReservaDetalleLog();

        $new->fill([
            'ct_reserva_id' => $ctRsLogId,
            'stock_id'      => $stockId,
            'art_code'      => $artCode,
            'bodega'        => $warehouse,
            'cantidad'      => $qty
        ]);
        return $new;
    }


    public function fromPayloadOrderReservedLog(int $reserveId, int $oldQuoteId, int $newQuoteId, int $userId, string $warehouse): GpPedidoReservaLog
    {
        $new = new GpPedidoReservaLog();
        $new->fill([
            'reserva_id'            => $reserveId,
            'cotizacion_origen_id'  => $oldQuoteId,
            'pedido_destino_id'     => $newQuoteId,
            'user_id'               => $userId,
            'bodega'                => $warehouse,
        ]);
        return $new;
    }

    public function formPayloadOrderReservedDetailLog(int $ctRsLogId, ?int $stockId, string $artCode, string $warehouse, float $qty): GpPedidoReservaDetalleLog
    {
        $new = new GpPedidoReservaDetalleLog();
        $new->fill([
            'pedido_reserva_id' => $ctRsLogId,
            'stock_id'          => $stockId,
            'art_code'          => $artCode,
            'bodega'            => $warehouse,
            'cantidad'          => $qty
        ]);
        return $new;
    }


    /** aqui funcion de traslado reservas formPayload */

    public function formPayloadTransferReserved(GpCotizacionDetalle $detail): GpTrasladosReservas
    {
        $tr = new GpTrasladosReservas();
        $tr->fill([
            'reserva_id'            => $detail->reserva_id,
            'cotizacion_id'         => $detail->cotizacion_id,
            'detalle_id'            => $detail->id,
            'stock_id'              => $detail->stock_id,
            'art_code'              => $detail->ART_CODIGO,
            'art_descripcion'       => $detail->ART_DESCRIPCION,
            'art_cod_tres_letras'   => $detail->ART_CODIGO_TRES_LETRAS,
            'art_plan_umedida'      => $detail->ART_PLAN_UMEDIDA,
            'art_peso'              => $detail->ART_PESO,
            'cantidad'              => $detail->seleccionado,
            'bodega_origen'         => $detail->bodega_origen,
            'bodega_destino'        => $detail->bodega_destino
        ]);
        return $tr;
    }





}
