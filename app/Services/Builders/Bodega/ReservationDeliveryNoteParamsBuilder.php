<?php

namespace App\Services\Builders\Bodega;

use App\Constants\Bodegas\DeliveryNoteConstants;
use App\Enums\bodegas\guiaremision\DeliveryNoteStatus;
use App\Enums\bodegas\guiaremision\ReasonForTransfer;
use App\Models\GpIngreso;
use App\Models\GpIngresosDetalle;
use App\Models\GpUsuario;
use App\Models\reservas\GpGuiaRemisionReserva;
use App\Models\reservas\GpGuiaRemisionReservaDetalle;
use App\Services\Builders\Bodega\Contracts\ReservationDeliveryNoteParamsBuilderInterface;
use Carbon\Carbon;

class ReservationDeliveryNoteParamsBuilder implements ReservationDeliveryNoteParamsBuilderInterface
{

    public function __construct()
    {
        // Inyecta dependencias si es necesario
    }


    public function buildReservationGuideHeader($payload, GpUsuario $user, array $datavehicle, string $secuencial, string $dirDestination, string $numGuide, string $bodGuide, string $dirOrigin): GpGuiaRemisionReserva
    {
        $guia = new GpGuiaRemisionReserva();
        $guia->fill([
            'numero_guia'                           => $numGuide,
            'user_id'                               => $user->id,
            'estado'                                => DeliveryNoteStatus::PENDIENTE->value,
            'bodega_origen'                         => $payload['bod_origen'],
            'bodega_destino'                        => $payload['bod_destino'],
            'guia_bodega'                           => $bodGuide,
            'establecimiento'                       => $payload['pto_origen'],
            'pto_emision'                           => DeliveryNoteConstants::PTO_EMISION_DEFAULT,
            'secuencial'                            => $secuencial,
            'direccion_partida'                     => $dirOrigin,
            'razon_social_transportista'            => $datavehicle['transportista']['nombre'],
            'identificacion_transportista'          => $datavehicle['transportista']['identificacion'],
            'tipo_identificacion_transportista'     => $datavehicle['transportista']['tipo_identificacion'],
            'placa'                                 => $datavehicle['vehiculo']['placa'],
            'fecha_salida'                          => $payload['fecha_salida'],
            'fecha_llegada'                         => $payload['fecha_llegada'],
            'observacion'                           => $payload['observacion'],
            'direccion_destinatario'                => $dirDestination,
            'identificacion_destinatario'           => DeliveryNoteConstants::DOC_DESTINATARIO_DEFAULT,
            'razon_social_destinatario'             => DeliveryNoteConstants::RAZON_SOCIAL_DESTINATARIO_DEFAULT,
            'ruta'                                  => $payload['ruta'],
            'fecha_emision_doc_sustento'            => DeliveryNoteConstants::FECHA_NULA_DATE,
        ]);
        return $guia;
    }

    public function buildReservationGuideDetails($data, $guiaId): GpGuiaRemisionReservaDetalle
    {
        $d = new GpGuiaRemisionReservaDetalle();
        $d->fill([
            'guia_id'               => $guiaId,
            'traslado_id'           => data_get($data, 'id'),
            'cotizacion_id'         => data_get($data, 'cotizacion_id'),
            'detalle_id'            => data_get($data, 'detalle_id'),
            'stock_id'              => data_get($data, 'stock_id'),
            'art_code'              => data_get($data, 'art_code'),
            'art_descripcion'       => data_get($data, 'art_descripcion'),
            'art_cod_tres_letras'   => data_get($data, 'art_cod_tres_letras'),
            'cantidad'              => (float) data_get($data, 'seleccionado'),
            'art_plan_umedida'      => data_get($data, 'art_plan_umedida'),
        ]);
        return $d;
    }




    public function buildReservationHeaderToGp($guia): array
    {
        return [
            'guiaRemision'                      => $guia->numero_guia,
            'establecimiento'                   => $guia->establecimiento,
            'ptoEmision'                        => $guia->pto_emision,
            'secuencial'                        => $guia->secuencial,
            'direccionPartida'                  => $guia->direccion_partida,
            'razonSocialTransportista'          => $guia->razon_social_transportista,
            'identificacionTransportista'       => $guia->identificacion_transportista,
            'tipoIdentificacionTransportista'   => $guia->tipo_identificacion_transportista,
            'rise'                              => '',
            'fechaInicio'                       => $guia->fecha_salida,
            'fechaFin'                          => $guia->fecha_llegada,
            'placa'                             => $guia->placa,
            'correoElectronico'                 => DeliveryNoteConstants::CORREO_NOTIFICACION,
            'observacion'                       => $guia->observacion,
            'fechaEmisionSustento'              => Carbon::parse(DeliveryNoteConstants::FECHA_NULA_DATETIME)->format('Y-m-d H:i:s'),
        ];
    }


    public function buildReservationDestinationToGp($guia): array
    {
        return [
            'guiaRemision'                  => $guia->numero_guia,
            'secuencialDestinatario'        => 1,
            'identificacionDestinatario'    => $guia->identificacion_destinatario,
            'razonSocialDestinatario'       => $guia->razon_social_destinatario,
            'direccionDestinatario'         => trim($guia->direccion_destinatario),
            'motivoTraslado'                => ReasonForTransfer::TRANSFERENCIA_RESERVA->value,
            'docAduanero'                   => '',
            'codEstablecimientoDestino'     => '',
            'descEstablecimientoDestino'    => '',
            'ruta'                          => $guia->ruta,
            'docSustento'                   => $guia->cod_sustento,
            'numDocSustento'                => $guia->doc_sustento,
            'numAutoDocSustento'            => $guia->num_auto_doc_sustento,
            'fechaEmisionDocSustento'       => Carbon::parse($guia->fecha_emision_doc_sustento)->format('Y-m-d H:i:s'),
        ];
    }


    public function builReservationDetailToGp(GpGuiaRemisionReserva $header, GpGuiaRemisionReservaDetalle $detail, int $indice): array
    {
        return [
            'guiaRemision'                  => $header->numero_guia,
            'secuencialDestinatario'        => 1,
            'secuenciaDetalle'              => $indice,
            'identificacionDestinatario'    => $header->identificacion_destinatario,
            'docSustento'                   => $header->doc_sustento,
            'codInterno'                    => $detail->art_code,
            'codAdicional'                  => '',
            'descripcion'                   => $detail->art_descripcion,
            'cantidad'                      => (float) $detail->cantidad,
            'unidadMedida'                  => $detail->art_plan_umedida,
            'guiaBodega'                    => $header->guia_bodega
        ];
    }



    public function buildReservedDeliveryNotePayload(GpGuiaRemisionReserva $guia, string $documento, array $items, string $user, string $hashInterno, ?string $numTranferencia): array
    {
        $header = [
            'idCompany'         => config('services.api_gp.company'),
            'pedido'            => $documento,
            'bodegaOrigen'      => $guia->bodega_origen,
            'bodegaDestino'     => $guia->bodega_destino,
            'observacion'       => preg_replace('/\s+/', ' ', $guia->observacion),
            'usuario'           => $user,
            'numTranferencia'   => $numTranferencia ?? '',
            'idGuiaRemision'    => $guia->id,
            'guiaRemision'      => $guia->numero_guia,
            'hashInterno'       => $hashInterno

        ];
        return [
            'cabecera'          => $header,
            'detalle'           => $items
        ];
    }


    public function buildReservationGuideValidationPayload(array $params, string $tipoMovimiento, string $tipo): array
    {
        return [
            'orderNumber'       => data_get($params, 'pedido'),
            'origen'            => data_get($params, 'bodegaOrigen'),
            'destino'           => data_get($params, 'bodegaDestino'),
            'idGuiaRemision'    => data_get($params, 'idGuiaRemision'),
            'guiaRemision'      => data_get($params, 'guiaRemision'),
            'tipoTrx'           => $tipoMovimiento,
            'tipo'              => $tipo,
            'hashInterno'       => data_get($params, 'hashInterno'),
        ];
    }


     public function createRegisterHistortyHeader($guia, GpUsuario $user, $quote): GpIngreso
    {
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

    public function createRegisterHistoryDetail($items, $ing): GpIngresosDetalle
    {
        $d = new GpIngresosDetalle();
        $d->fill([
            'ingreso_id'    => $ing->id,
            'ART_CODIGO'    => $items['art_code'],
            'ingresado'     => $items['recibir'],
        ]);
        return $d;
    }
}
