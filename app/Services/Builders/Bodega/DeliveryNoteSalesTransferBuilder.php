<?php

namespace App\Services\Builders\Bodega;

use App\Constants\Bodegas\DeliveryNoteConstants;
use App\Enums\bodegas\guiaremision\DeliveryNoteStatus;
use App\Models\despachos\GpGuiaRemision;
use App\Models\GpIngreso;
use App\Models\GpIngresosDetalle;
use App\Models\GpUsuario;
use App\Services\Builders\Bodega\Contracts\DeliveryNoteSalesTransferBuilderInterface;
use Carbon\Carbon;

class DeliveryNoteSalesTransferBuilder implements DeliveryNoteSalesTransferBuilderInterface
{

    public function buildDeliveryNoteSalesTransferHeader(array $payload, array $infoGuia, array $dataVehicle): GpGuiaRemision
    {
        $gr = new GpGuiaRemision();
        $gr->fill([
            'tipo'                                          => data_get($infoGuia, 'tipo'),
            'estado'                                        => DeliveryNoteStatus::PENDIENTE->value,
            'pedido'                                        => data_get($infoGuia, 'documento'),
            'bodega_origen'                                 => data_get($infoGuia, 'bodegaOrigen'),
            'bodega_destino'                                => data_get($infoGuia, 'bodegaDestino'),
            'user_id'                                       => data_get($infoGuia, 'user.id'),
            'GUI_REM_BODEGA'                                => data_get($infoGuia, 'bodGuide'),
            'GUI_REM_PTO_EMISION'                           => data_get($infoGuia,  'ptoEmision'),
            'GUI_REM_ESTABLECIMIENTO'                       => data_get($infoGuia, 'ptoOrigen'),
            'GUI_REM_SECUENCIAL'                            => data_get($infoGuia, 'secuencial'),
            'GUI_REM_GUIA_REMISION'                         => data_get($infoGuia, 'numeroGuia'),
            'GUI_REM_DIRECCION_PARTIDA'                     => data_get($infoGuia, 'dirOrigin'),
            'idempotency_key'                               => data_get($infoGuia, 'idemKey'),
            'GUI_REM_RAZON_SOCIAL_TRANSPORTISTA'            => data_get($dataVehicle, 'transportista.nombre'),
            'GUI_REM_IDENTIFICACION_TRANSPORTISTA'          => data_get($dataVehicle, 'transportista.identificacion'),
            'GUI_REM_TIPO_IDENTIFICACION_TRANSPORTISTA'     => data_get($dataVehicle, 'transportista.tipo_identificacion'),
            'GUI_REM_FECHA_INICIO'                          => data_get($payload, 'fecha_salida'),
            'GUI_REM_FECHA_FIN'                             => data_get($payload, 'fecha_llegada'),
            'GUI_REM_PLACA'                                 => data_get($dataVehicle, 'vehiculo.placa'),
            'GUI_REM_OBSERVACION'                           => data_get($payload, 'observacion'),
            'GUI_REM_RAZON_SOCIAL_DESTINATARIO'             => DeliveryNoteConstants::RAZON_SOCIAL_DESTINATARIO_DEFAULT,
            'GUI_REM_DIRECCION_DESTINATARIO'                => data_get($infoGuia, 'dirDestino'),
            'GUI_REM_IDENTIFICACION_DESTINATARIO'           => DeliveryNoteConstants::DOC_DESTINATARIO_DEFAULT,
            'GUI_REM_MOTIVO_TRASLADO'                       => data_get($infoGuia, 'motivoTraslado'),
            'GUI_REM_RUTA'                                  => data_get($payload, 'ruta', ''),
            'GUI_REM_CODI_DOCU_SUSTENTO'                    => '',
            'GUI_REM_NUME_DOCU_SUSTENTO'                    => '',
            'GUI_REM_NUME_AUTO_DOCU_SUSTENTO'               => '',
            'GUI_REM_FECHA_EMISION_DOCU_SUSTENTO'           => Carbon::parse(DeliveryNoteConstants::FECHA_NULA_DATE)->format('Y-m-d H:i:s'),
        ]);
        return $gr;
    }
     public function buildDeliveryNoteReplenishmentTransferHeader(array $payload, array $infoGuia): GpGuiaRemision
    {
        $gr = new GpGuiaRemision();
        $gr->fill([
            'tipo'                                          => data_get($infoGuia, 'tipo'),
            'estado'                                        => DeliveryNoteStatus::PENDIENTE->value,
            'pedido'                                        => data_get($infoGuia, 'documento'),
            'bodega_origen'                                 => data_get($infoGuia, 'bodegaOrigen'),
            'bodega_destino'                                => data_get($infoGuia, 'bodegaDestino'),
            'user_id'                                       => data_get($infoGuia, 'user.id'),
            'GUI_REM_BODEGA'                                => data_get($infoGuia, 'bodGuide'),
            'GUI_REM_PTO_EMISION'                           => data_get($infoGuia,  'ptoEmision'),
            'GUI_REM_ESTABLECIMIENTO'                       => data_get($infoGuia, 'ptoOrigen'),
            'GUI_REM_SECUENCIAL'                            => data_get($infoGuia, 'secuencial'),
            'GUI_REM_GUIA_REMISION'                         => data_get($infoGuia, 'numeroGuia'),
            'GUI_REM_DIRECCION_PARTIDA'                     => data_get($infoGuia, 'dirOrigin'),
            'idempotency_key'                               => data_get($infoGuia, 'idemKey'),
            'GUI_REM_RAZON_SOCIAL_TRANSPORTISTA'            => data_get($payload, 'transportista.nombre'),
            'GUI_REM_IDENTIFICACION_TRANSPORTISTA'          => data_get($payload, 'transportista.identificacion'),
            'GUI_REM_TIPO_IDENTIFICACION_TRANSPORTISTA'     => data_get($payload, 'transportista.tipo_identificacion'),
            'GUI_REM_FECHA_INICIO'                          => data_get($payload, 'fecha_inicio'),
            'GUI_REM_FECHA_FIN'                             => data_get($payload, 'fecha_fin'),
            'GUI_REM_PLACA'                                 => data_get($payload, 'vehiculo.placa'),
            'GUI_REM_OBSERVACION'                           => data_get($payload, 'observacion'),
            'GUI_REM_RAZON_SOCIAL_DESTINATARIO'             => DeliveryNoteConstants::RAZON_SOCIAL_DESTINATARIO_DEFAULT,
            'GUI_REM_DIRECCION_DESTINATARIO'                => data_get($infoGuia, 'dirDestino'),
            'GUI_REM_IDENTIFICACION_DESTINATARIO'           => DeliveryNoteConstants::DOC_DESTINATARIO_DEFAULT,
            'GUI_REM_MOTIVO_TRASLADO'                       => data_get($infoGuia, 'motivoTraslado'),
            'GUI_REM_RUTA'                                  => data_get($payload, 'ruta', ''),
            'GUI_REM_CODI_DOCU_SUSTENTO'                    => '',
            'GUI_REM_NUME_DOCU_SUSTENTO'                    => '',
            'GUI_REM_NUME_AUTO_DOCU_SUSTENTO'               => '',
            'GUI_REM_FECHA_EMISION_DOCU_SUSTENTO'           => Carbon::parse(DeliveryNoteConstants::FECHA_NULA_DATE)->format('Y-m-d H:i:s'),
        ]);
        return $gr;
    }








    public function buildDeliveryNoteReceivedPayload(GpGuiaRemision $guia, string $documento, array $items, string $user, string $hash, ?string $movimiento): array
    {
        $header = [
            'idCompany'         => config('services.api_gp.company'),
            'pedido'            => $documento,
            'bodegaOrigen'      => $guia->bodega_origen,
            'bodegaDestino'     => $guia->bodega_destino,
            'observacion'       => preg_replace('/\s+/', ' ', $guia->GUI_REM_OBSERVACION),
            'usuario'           => $user,
            'numTranferencia'   => $movimiento ?? '',
            'idGuiaRemision'    => $guia->id,
            'guiaRemision'      => $guia->GUI_REM_GUIA_REMISION,
            'hashInterno'       => $hash
        ];
        return [
            'cabecera'          => $header,
            'detalle'           => $items
        ];
    }



    public function buildDeliveryNoteValidateReceivedSalesTransfer(array $params, string $tipoMovimiento, string $tipo): array
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



    public function createRegisterHistoryHeader(GpGuiaRemision $guia, GpUsuario $user, string $document, string $type): GpIngreso
    {
        $ing = new GpIngreso();
        $ing->fill([
            'user_id'               => $user->id,
            'USERID'                => $user->USERID,
            'tipo'                  => $type,
            'documento'             => $document,
            'GUI_REM_GUIA_REMISION' => $guia->GUI_REM_GUIA_REMISION,
        ]);
        return $ing;
    }

    public function ceateRegisterHistoryDetail(array $item, GpIngreso $ing): GpIngresosDetalle
    {
        $d = new GpIngresosDetalle();
        $d->fill([
            'ingreso_id'    => $ing->id,
            'ART_CODIGO'    => data_get($item, 'GUI_REM_CODI_INTERNO'),
            'ingresado'     => data_get($item, 'recibido'),
        ]);
        return $d;
    }
}
