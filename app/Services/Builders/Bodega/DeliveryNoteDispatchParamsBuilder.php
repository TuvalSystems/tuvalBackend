<?php

namespace App\Services\Builders\Bodega;

use App\Models\despachos\GpGuiaRemision;
use App\Models\despachos\GpGuiaRemisionDetalle;
use App\Services\Builders\Bodega\Contracts\DeliveryNoteDispatchParamsBuilderInterface;
use Carbon\Carbon;

class DeliveryNoteDispatchParamsBuilder implements DeliveryNoteDispatchParamsBuilderInterface
{

    public function __construct()
    {
        // Inyecta dependencias si es necesario
    }



    public function buildDeliveryNoteDispatchHeader($payload, $infoGuia): GpGuiaRemision
    {
        $gr = new GpGuiaRemision();
        $gr->fill([
            'tipo'              => data_get($infoGuia, 'tipo'),
            'idempotency_key'   => data_get($infoGuia, 'idemKey'),
            'estado'            => 'pendiente',
            'bodega_origen'     => data_get($infoGuia, 'pedido.BOD_BODEGA'),
        ]);

        return $gr;
    }

    public function buildDeliveryNoteHeaderToGp($guia): array
    {
        return [
            'guiaRemision'                      => $guia->GUI_REM_GUIA_REMISION,
            'establecimiento'                   => $guia->GUI_REM_ESTABLECIMIENTO,
            'ptoEmision'                        => $guia->GUI_REM_PTO_EMISION,
            'secuencial'                        => $guia->GUI_REM_SECUENCIAL,
            'direccionPartida'                  => $guia->GUI_REM_DIRECCION_PARTIDA,
            'razonSocialTransportista'          => $guia->GUI_REM_RAZON_SOCIAL_TRANSPORTISTA,
            'identificacionTransportista'       => $guia->GUI_REM_IDENTIFICACION_TRANSPORTISTA,
            'tipoIdentificacionTransportista'   => $guia->GUI_REM_TIPO_IDENTIFICACION_TRANSPORTISTA,
            'rise'                              => '',
            'fechaInicio'                       => $guia->GUI_REM_FECHA_INICIO,
            'fechaFin'                          => $guia->GUI_REM_FECHA_FIN,
            'placa'                             => $guia->GUI_REM_PLACA,
            'correoElectronico'                 => 'guias@tuvalsa.com',
            'observacion'                       => $guia->GUI_REM_OBSERVACION,
            'fechaEmisionSustento'              => Carbon::parse('1900-01-01 00:00:00')->format('Y-m-d H:i:s'),
        ];
    }


    public function buildDeliveryNoteDestinationToGp($guia): array
    {
        return [
            'guiaRemision'                  => $guia->GUI_REM_GUIA_REMISION,
            'secuencialDestinatario'        => 1,
            'identificacionDestinatario'    => $guia->GUI_REM_IDENTIFICACION_DESTINATARIO,
            'razonSocialDestinatario'       => $guia->GUI_REM_RAZON_SOCIAL_DESTINATARIO,
            'direccionDestinatario'         => trim($guia->GUI_REM_DIRECCION_DESTINATARIO),
            'motivoTraslado'                => $guia->GUI_REM_MOTIVO_TRASLADO,
            'docAduanero'                   => '',
            'codEstablecimientoDestino'     => '',
            'descEstablecimientoDestino'    => '',
            'ruta'                          => $guia->GUI_REM_RUTA,
            'docSustento'                   => $guia->GUI_REM_CODI_DOCU_SUSTENTO,
            'numDocSustento'                => $guia->GUI_REM_NUME_DOCU_SUSTENTO,
            'numAutoDocSustento'            => $guia->GUI_REM_NUME_AUTO_DOCU_SUSTENTO,
            'fechaEmisionDocSustento'       => Carbon::parse($guia->GUI_REM_FECHA_EMISION_DOCU_SUSTENTO)->format('Y-m-d H:i:s'),
        ];
    }


    public function buildDeliveryNoteDetailToGp(GpGuiaRemision $header, GpGuiaRemisionDetalle $detail, int $indice): array
    {
        return [
            'guiaRemision'                  => $header->GUI_REM_GUIA_REMISION,
            'secuencialDestinatario'        => 1,
            'secuenciaDetalle'              => $indice,
            'identificacionDestinatario'    => $header->GUI_REM_IDENTIFICACION_DESTINATARIO,
            'docSustento'                   => $header->GUI_REM_NUME_DOCU_SUSTENTO,
            'codInterno'                    => $detail->GUI_REM_CODI_INTERNO,
            'codAdicional'                  => '',
            'descripcion'                   => $detail->GUI_REM_DESCRIPCION,
            'cantidad'                      => (float) $detail->GUI_REM_CANTIDAD,
            'unidadMedida'                  => $detail->GUI_REM_UNIDAD_MEDIDA,
            'guiaBodega'                    => $header->GUI_REM_BODEGA
        ];
    }
}
