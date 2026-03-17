<?php

namespace App\Services\Builders\Bodega;

use App\Constants\Bodegas\DeliveryNoteConstants;
use App\Enums\bodegas\guiaremision\DeliveryNoteStatus;
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
            'tipo'                                      => data_get($infoGuia, 'tipo'),
            'estado'                                    => DeliveryNoteStatus::PENDIENTE->value,
            'pedido'                                    => data_get($infoGuia, 'pedido.pedido'),
            'bodega_origen'                             => data_get($infoGuia, 'pedido.BOD_BODEGA'),
            'bodega_destino'                            => null,
            'user_id'                                   => data_get($infoGuia, 'user.id'),
            'GUI_REM_BODEGA'                            => data_get($infoGuia, 'bodGuide'),
            'GUI_REM_PTO_EMISION'                       => data_get($infoGuia,  'ptoEmision'),
            'GUI_REM_ESTABLECIMIENTO'                   => data_get($infoGuia, 'ptoVta'),
            'GUI_REM_SECUENCIAL'                        => data_get($infoGuia, 'secuencial'),
            'GUI_REM_GUIA_REMISION'                     => data_get($infoGuia, 'numeroGuia'),
            'GUI_REM_DIRECCION_PARTIDA'                 => data_get($infoGuia, 'dirOrigin'),
            'idempotency_key'                           => data_get($infoGuia, 'idemKey'),
            'GUI_REM_RAZON_SOCIAL_TRANSPORTISTA'        => data_get($payload, 'transportista.nombre'),
            'GUI_REM_IDENTIFICACION_TRANSPORTISTA'      => data_get($payload, 'transportista.identificacion'),
            'GUI_REM_TIPO_IDENTIFICACION_TRANSPORTISTA' => data_get($payload, 'transportista.tipo_identificacion'),
            'GUI_REM_FECHA_INICIO'                      => data_get($payload, 'fecha_inicio'),
            'GUI_REM_FECHA_FIN'                         => data_get($payload, 'fecha_fin'),
            'GUI_REM_PLACA'                             => data_get($payload, 'vehiculo.placa'),
            'GUI_REM_OBSERVACION'                       => data_get($payload, 'observacion'),
            'GUI_REM_RAZON_SOCIAL_DESTINATARIO'         => data_get($infoGuia, 'pedido.CLI_RAZON_SOCIAL'),
            'GUI_REM_DIRECCION_DESTINATARIO'            => data_get($infoGuia, 'direccionEnvio'),
            'GUI_REM_IDENTIFICACION_DESTINATARIO'       => data_get($infoGuia, 'pedido.CLI_RUC'),
            'GUI_REM_MOTIVO_TRASLADO'                   => data_get($infoGuia, 'motivoTraslado'),
            'GUI_REM_RUTA'                              => data_get($payload, 'ruta', ''),
            'GUI_REM_CODI_DOCU_SUSTENTO'                => data_get($infoGuia, 'docuSustento', '01'),
            'GUI_REM_NUME_DOCU_SUSTENTO'                => data_get($infoGuia, 'pedido.factura', ''),
            'GUI_REM_NUME_AUTO_DOCU_SUSTENTO'           => '',
            'GUI_REM_FECHA_EMISION_DOCU_SUSTENTO'       => Carbon::parse(data_get($infoGuia, 'pedido.fecha_factura', '1900-01-01 00:00:00'))->format('Y-m-d H:i:s'),
        ]);
        return $gr;
    }


    public function buildDeliveryNoteDispatchDetail($guiaId, $detalle): GpGuiaRemisionDetalle
    {
        $new = new GpGuiaRemisionDetalle();
        $new->fill([
            'guia_remision_id'          => $guiaId,
            'detalle_id'                => data_get($detalle, 'id'),
            'GUI_REM_CODI_INTERNO'      => data_get($detalle, 'ART_CODIGO'),
            'GUI_REM_DESCRIPCION'       => data_get($detalle, 'ART_DESCRIPCION'),
            'GUI_REM_CODI_ADICIONAL'    => data_get($detalle, 'ART_CODIGO_TRES_LETRAS'),
            'GUI_REM_CANTIDAD'          => (float)data_get($detalle, 'seleccionado'),
            'GUI_REM_UNIDAD_MEDIDA'     => data_get($detalle, 'ART_PLAN_UMEDIDA'),
        ]);
        return $new;
    }

    public function buildDeliveryNoteHeaderToGp($guia, ?string $ordenCompra = null): array
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
            'correoElectronico'                 => DeliveryNoteConstants::CORREO_NOTIFICACION,
            'observacion'                       => $guia->GUI_REM_OBSERVACION,
            'fechaEmisionSustento'              => Carbon::parse(DeliveryNoteConstants::FECHA_NULA_DATETIME)->format('Y-m-d H:i:s'),
            'ordenCompra'                       => $ordenCompra ?? '',
        ];
    }


    public function buildDeliveryNoteDestinationToGp($guia, ?string $ordenCompra = null): array
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
            'ruta'                          => $guia->GUI_REM_RUTA ?? '',
            'docSustento'                   => $guia->GUI_REM_CODI_DOCU_SUSTENTO,
            'numDocSustento'                => $guia->GUI_REM_NUME_DOCU_SUSTENTO,
            'numAutoDocSustento'            => $guia->GUI_REM_NUME_AUTO_DOCU_SUSTENTO,
            'fechaEmisionDocSustento'       => Carbon::parse($guia->GUI_REM_FECHA_EMISION_DOCU_SUSTENTO)->format('Y-m-d H:i:s'),
            'ordenCompra'                   => $ordenCompra ?? '',
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


    public function buildParameterHeaderAndDetailDispatchShipment(GpGuiaRemision $guia, string $documento, string $newOrder, array $items, $user, string $hashInterno): array
    {
        $header = [
            'idCompany'         => config('services.api_gp.company'),
            'pedido'            => $documento,
            'pedidoNuevo'       => $newOrder,
            'bodegaOrigen'      => $guia->bodega_origen,
            'observacion'       => $guia->GUI_REM_OBSERVACION,
            'usuario'           => $user->USERID ?? 'adminWeb',
            'idGuiaRemision'    => $guia->id,
            'guiaRemision'      => $guia->GUI_REM_GUIA_REMISION,
            'hashInterno'       => $hashInterno
        ];
        return [
            'cabecera'          => $header,
            'detalle'           => $items
        ];
    }


    public function buildDeliveryNoteValidateDispatchShipment(array $params,  string $tipoMovimiento, string $tipo): array{
        return [
            'orderNumber'       => data_get($params, 'pedido'),
            'newOrderNumber'    => data_get($params, 'pedidoNuevo'),
            'origen'            => data_get($params, 'bodegaOrigen'),
            'idGuiaRemision'    => data_get($params, 'idGuiaRemision'),
            'guiaRemision'      => data_get($params, 'guiaRemision'),
            'tipoTrx'           => $tipoMovimiento,
            'tipo'              => $tipo,
            'hashInterno'       => data_get($params, 'hashInterno'),
        ];
    }
}
