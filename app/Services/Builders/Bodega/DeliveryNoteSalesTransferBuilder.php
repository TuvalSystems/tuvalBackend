<?php

namespace App\Services\Builders\Bodega;

use App\Constants\Bodegas\DeliveryNoteConstants;
use App\Enums\bodegas\guiaremision\DeliveryNoteStatus;
use App\Models\despachos\GpGuiaRemision;
use App\Services\Builders\Bodega\Contracts\DeliveryNoteSalesTransferBuilderInterface;
use Carbon\Carbon;

class DeliveryNoteSalesTransferBuilder implements DeliveryNoteSalesTransferBuilderInterface
{

    public function buildDeliveryNoteSalesTransferHeader($payload, $infoGuia): GpGuiaRemision
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
}
