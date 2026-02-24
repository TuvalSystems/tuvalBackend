<?php

namespace App\Services\Builders\Venta;

use App\Models\GpIngreso;
use App\Models\GpIngresosDetalle;
use App\Models\GpUsuario;
use App\Models\reservas\GpCotizacionReservaDetalleLog;
use App\Models\reservas\GpCotizacionReservaLog;
use App\Models\reservas\GpGuiaRemisionReserva;
use App\Models\reservas\GpPedidoReservaDetalleLog;
use App\Models\reservas\GpPedidoReservaLog;
use App\Models\reservas\GpReserva;
use App\Models\reservas\GpTrasladosReservas;
use App\Services\Builders\Venta\Contracts\ReservedBuilderInterface;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;

class ReservedBuilder implements ReservedBuilderInterface
{

    public function __construct()
    {
        // Inyecta dependencias si es necesario
    }


    public function fromPayloadReserved(array $data, GpUsuario $user)
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



    public function fromPayloadQuoteReservedLog($reserveId, $oldQuoteId, $newQuoteId, $userId, $warehouse)
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


    public function fromPayloadQuoteReservedDetailLog($ctRsLogId, $stockId, $artCode, $warehouse, $qty)
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


    public function fromPayloadOrderReservedLog($reserveId, $oldQuoteId, $newQuoteId, $userId, $warehouse)
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

    public function formPayloadOrderReservedDetailLog($ctRsLogId, $stockId, $artCode, $warehouse, $qty)
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

    public function formPayloadTransferReserved($detail)
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



    public function formPayloadReservationReceivedDetail(array $items, Collection $movimientos, Collection $traslados): array
    {
        $groups = [];
        foreach ($items as $itm) {
            $trm = $traslados->get($itm['traslado_id']);
            if (!$trm) {
                continue;
            }
            $cot                = $itm['c_cotizacion'];
            $mov                = $movimientos->get($itm['c_cotizacion']) ?? null;
            $itm['traslado']    = $trm;

            $groups[$cot]['pedido']         = $cot;
            $groups[$cot]['items'][]        = $itm;
            $groups[$cot]['transferencia']  = $mov?->codigo ?? '';
            $groups[$cot]['ws_items'][] = [
                'item'                      => Arr::get($itm, 'art_code', ''),
                'compartimiento'            => Arr::get($itm, 'compartimiento', ''),
                'cantidad'                  => (float) ($itm['recibir'] ?? 0)
            ];
        }
        return $groups;
    }



    public function buildReservationDeliveryNoteReceivedPayload(GpGuiaRemisionReserva $guia, string $documento, array $items, string $movimiento, string $user): array
    {

        $header = [
            'idCompany'         => config('services.api_gp.company'),
            'pedido'            => $documento,
            'bodegaOrigen'      => $guia->bodega_origen,
            'bodegaDestino'     => $guia->bodega_destino,
            'observacion'       => preg_replace('/\s+/', ' ', $guia->observacion),
            'usuario'           => $user,
            'numTranferencia'   => $movimiento,
            'idGuiaRemision'    => $guia->id,
            'guiaRemision'      => $guia->numero_guia
        ];
        return [
            'cabecera'  => $header,
            'detalle'   => $items
        ];
    }


    public function buildRecievedDeliveryNoteValidationPayload(array $params): array
    {
        return [
            'orderNumber'       => data_get($params, 'pedido'),
            'newOrderNumber'    => "",
            'origen'            => data_get($params, 'bodegaOrigen'),
            'idGuiaRemision'    => data_get($params, 'idGuiaRemision'),
            'guiaRemision'      => data_get($params, 'guiaRemision'),
        ];
    }



    /**guardar en tablas de ingresos */
     public function createRegisterHistortyHeader($guia, GpUsuario $user, $quote): GpIngreso{
        $ing = new GpIngreso();
        $ing->fill([
            'user_id'               => $user->id,
            'USERID'                => $user->USERID,
            'tipo'                  => 'traslado_reserva',
            'documento'             => $quote,
            'GUI_REM_GUIA_REMISION' => $guia->numero_guia,
        ]);
        return $ing;
    }

    public function createRegisterHistoryDetail($items, $ing): GpIngresosDetalle{
        $d = new GpIngresosDetalle();
        $d->fill([
            'ingreso_id'    => $ing->id,
            'ART_CODIGO'    => $items['art_code'],
            'ingresado'     => $items['recibir'],
        ]);
        return $d;
    }


}
