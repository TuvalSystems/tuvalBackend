<?php

namespace App\Models\despachos;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\despachos\GpGuiaRemision
 *
 * @property int $id
 * @property string $tipo
 * @property string $estado
 * @property string $bodega_origen
 * @property string $bodega_destino
 * @property string $pedido
 * @property int|null $user_id
 * @property string $GUI_REM_BODEGA
 * @property string $GUI_REM_GUIA_REMISION
 * @property string $GUI_REM_ESTABLECIMIENTO
 * @property string $GUI_REM_PTO_EMISION
 * @property string $GUI_REM_SECUENCIAL
 * @property string $GUI_REM_DIRECCION_PARTIDA
 * @property string $GUI_REM_RAZON_SOCIAL_TRANSPORTISTA
 * @property string $GUI_REM_IDENTIFICACION_TRANSPORTISTA
 * @property string $GUI_REM_TIPO_IDENTIFICACION_TRANSPORTISTA
 * @property string $GUI_REM_FECHA_INICIO
 * @property string $GUI_REM_FECHA_FIN
 * @property string $GUI_REM_PLACA
 * @property string $GUI_REM_OBSERVACION
 * @property string $GUI_REM_RAZON_SOCIAL_DESTINATARIO
 * @property string $GUI_REM_DIRECCION_DESTINATARIO
 * @property string $GUI_REM_IDENTIFICACION_DESTINATARIO
 * @property string $GUI_REM_MOTIVO_TRASLADO
 * @property string $GUI_REM_RUTA
 * @property string|null $GUI_REM_CODI_DOCU_SUSTENTO
 * @property string|null $GUI_REM_NUME_DOCU_SUSTENTO
 * @property string|null $GUI_REM_NUME_AUTO_DOCU_SUSTENTO
 * @property string|null $GUI_REM_FECHA_EMISION_DOCU_SUSTENTO
 * @property int|null $rebajado
 * @property int|null $procesando
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemision newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemision newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemision query()
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemision whereBodegaOrigen($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemision whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemision whereGUIREMCODIDOCUSUSTENTO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemision whereGUIREMDIRECCIONDESTINATARIO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemision whereGUIREMDIRECCIONPARTIDA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemision whereGUIREMESTABLECIMIENTO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemision whereGUIREMFECHAEMISIONDOCUSUSTENTO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemision whereGUIREMFECHAFIN($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemision whereGUIREMFECHAINICIO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemision whereGUIREMGUIAREMISION($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemision whereGUIREMIDENTIFICACIONTRANSPORTISTA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemision whereGUIREMMOTIVOTRASLADO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemision whereGUIREMNUMEAUTODOCUSUSTENTO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemision whereGUIREMNUMEDOCUSUSTENTO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemision whereGUIREMOBSERVACION($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemision whereGUIREMPLACA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemision whereGUIREMPTOEMISION($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemision whereGUIREMRAZONSOCIALTRANSPORTISTA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemision whereGUIREMRUTA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemision whereGUIREMSECUENCIAL($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemision whereGUIREMTIPOIDENTIFICACIONTRANSPORTISTA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemision whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemision whereTipo($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemision whereUpdatedAt($value)
 * @mixin \Eloquent
 * @property string|null $GUI_REM_CODIGO
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemision whereBodegaDestino($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemision whereEstado($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemision whereGUIREMBODEGA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemision whereGUIREMCODIGO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemision whereGUIREMIDENTIFICACIONDESTINATARIO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemision whereGUIREMRAZONSOCIALDESTINATARIO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemision wherePedido($value)
 */
class GpGuiaRemision extends Model
{
    use HasFactory;
}
