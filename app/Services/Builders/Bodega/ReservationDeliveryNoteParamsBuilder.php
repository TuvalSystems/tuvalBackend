<?php

namespace App\Services\Builders\Bodega;

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
            'numero_guia' => $numGuide,
            'user_id' => $user->id,
            'estado' => 'pendiente',
            'bodega_origen' => $payload['bod_origen'],
            'bodega_destino' => $payload['bod_destino'],
            'guia_bodega' => $bodGuide,
            'establecimiento' => $payload['pto_origen'],
            'pto_emision' => '001',
            'secuencial' => $secuencial,
            'direccion_partida' => $dirOrigin,
            'razon_social_transportista' => $datavehicle['transportista']['nombre'],
            'identificacion_transportista' => $datavehicle['transportista']['identificacion'],
            'tipo_identificacion_transportista' => $datavehicle['transportista']['tipo_identificacion'],
            'placa' => $datavehicle['vehiculo']['placa'],
            'fecha_salida' => $payload['fecha_salida'],
            'fecha_llegada' => $payload['fecha_llegada'],
            'observacion' => $payload['observacion'],
            'direccion_destinatario' => $dirDestination,
            'identificacion_destinatario' => '0990281866001',
            'razon_social_destinatario' => 'TUVAL S.A.',
            'ruta' => $payload['ruta'],
            'fecha_emision_doc_sustento' => '1900-01-01',
        ]);
        return $guia;
    }

    public function buildReservationGuideDetails($data, $guiaId): GpGuiaRemisionReservaDetalle
    {
        $d = new GpGuiaRemisionReservaDetalle();
        $d->fill([
            'guia_id' => $guiaId,
            'traslado_id' => $data['id'],
            'cotizacion_id' => $data['cotizacion_id'],
            'detalle_id' => $data['detalle_id'],
            'stock_id' => $data['stock_id'],
            'art_code' => $data['art_code'],
            'art_descripcion' => $data['art_descripcion'],
            'art_cod_tres_letras' => $data['art_cod_tres_letras'],
            'cantidad' => $data['seleccionado'],
            'art_plan_umedida' => $data['art_plan_umedida']
        ]);
        return $d;
    }




    public function buildReservationHeaderToGp($guia): array
    {
        return [
            'guiaRemision' => $guia->numero_guia,
            'establecimiento' => $guia->establecimiento,
            'ptoEmision' => $guia->pto_emision,
            'secuencial' => $guia->secuencial,
            'direccionPartida' => $guia->direccion_partida,
            'razonSocialTransportista' => $guia->razon_social_transportista,
            'identificacionTransportista' => $guia->identificacion_transportista,
            'tipoIdentificacionTransportista' => $guia->tipo_identificacion_transportista,
            'rise' => '',
            'fechaInicio' => $guia->fecha_salida,
            'fechaFin' => $guia->fecha_llegada,
            'placa' => $guia->placa,
            'correoElectronico' => 'guias@tuvalsa.com',
            'observacion' => $guia->observacion,
            'fechaEmisionSustento' => Carbon::parse('1900-01-01 00:00:00')->format('Y-m-d H:i:s'),
        ];
    }


    public function buildReservationDestinationToGp($guia): array
    {
        return [
            'guiaRemision' => $guia->numero_guia,
            'secuencialDestinatario' => 1,
            'identificacionDestinatario' => $guia->identificacion_destinatario,
            'razonSocialDestinatario' => $guia->razon_social_destinatario,
            'direccionDestinatario' => trim($guia->direccion_destinatario),
            'motivoTraslado' => 'transferencia_reserva',
            'docAduanero' => '',
            'codEstablecimientoDestino' => '',
            'descEstablecimientoDestino' => '',
            'ruta' => $guia->ruta,
            'docSustento' => $guia->cod_sustento,
            'numDocSustento' => $guia->doc_sustento,
            'numAutoDocSustento' => $guia->num_auto_doc_sustento,
            'fechaEmisionDocSustento' => Carbon::parse($guia->fecha_emision_doc_sustento)->format('Y-m-d H:i:s'),
        ];
    }


    public function builReservationDetailToGp(GpGuiaRemisionReserva $header, GpGuiaRemisionReservaDetalle $detail, int $indice): array
    {
        return [
            'guiaRemision' => $header->numero_guia,
            'secuencialDestinatario' => 1,
            'secuenciaDetalle' => $indice,
            'identificacionDestinatario' => $header->identificacion_destinatario,
            'docSustento' => $header->doc_sustento,
            'codInterno' => $detail->art_code,
            'codAdicional' => '',
            'descripcion' => $detail->art_descripcion,
            'cantidad' => (int) $detail->cantidad,
            'unidadMedida' => $detail->art_plan_umedida,
            'guiaBodega' => $header->guia_bodega
        ];
    }



    public function buildReservedDeliveryNotePayload(GpGuiaRemisionReserva $guia, string $documento, array $items, string $user): array
    {
        $header = [
            'idCompany' => config('services.api_gp.company'),
            'pedido' => $documento,
            'bodegaOrigen' => $guia->bodega_origen,
            'bodegaDestino' => $guia->bodega_destino,
            'observacion' => preg_replace('/\s+/', ' ', $guia->observacion),
            'usuario' => $user,
            'numTranferencia' => '',
            'idGuiaRemision' => $guia->id,
            'guiaRemision' => $guia->numero_guia
        ];
        return [
            'cabecera' => $header,
            'detalle' => $items
        ];
    }


    public function buildReservationGuideValidationPayload(array $params): array
    {
        return [
            'orderNumber' => data_get($params, 'pedido'),
            'newOrderNumber' => "",
            'origen' => data_get($params, 'bodegaOrigen'),
            'idGuiaRemision' => data_get($params, 'idGuiaRemision'),
            'guiaRemision' => data_get($params, 'guiaRemision'),
        ];
    }
}
