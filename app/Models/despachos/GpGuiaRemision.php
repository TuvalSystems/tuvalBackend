<?php

namespace App\Models\despachos;

use App\Models\GpBodega;
use App\Models\Concerns\HasDeliveryNoteWarehouses;
use App\Models\GpRelacionPuntoVentaBodega;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\despachos\GpGuiaRemision
 *
 * @property int $id
 * @property string|null $GUI_REM_CODIGO
 * @property string $tipo
 * @property string $estado
 * @property string $bodega_origen
 * @property string|null $bodega_destino
 * @property int|null $bodega_origen_id
 * @property int|null $bodega_destino_id
 * @property string|null $pedido
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
 * @property string|null $GUI_REM_OBSERVACION
 * @property string $GUI_REM_DIRECCION_DESTINATARIO
 * @property string $GUI_REM_RAZON_SOCIAL_DESTINATARIO
 * @property string $GUI_REM_IDENTIFICACION_DESTINATARIO
 * @property string $GUI_REM_MOTIVO_TRASLADO
 * @property string|null $GUI_REM_RUTA
 * @property string|null $GUI_REM_CODI_DOCU_SUSTENTO
 * @property string|null $GUI_REM_NUME_DOCU_SUSTENTO
 * @property string|null $GUI_REM_NUME_AUTO_DOCU_SUSTENTO
 * @property string|null $GUI_REM_FECHA_EMISION_DOCU_SUSTENTO
 * @property int|null $rebajado
 * @property int|null $recibidos
 * @property string|null $idempotency_key
 * @property string|null $orden_compra
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int|null $user_id
 * @property int|null $procesando
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\despachos\GpGuiaRemisionDespacho> $guiadespacho
 * @property-read int|null $guiadespacho_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\despachos\GpGuiaRemisionDetalle> $guiadetalle
 * @property-read int|null $guiadetalle_count
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemision newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemision newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemision query()
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemision whereBodegaDestino($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemision whereBodegaOrigen($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemision whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemision whereEstado($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemision whereGUIREMBODEGA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemision whereGUIREMCODIDOCUSUSTENTO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemision whereGUIREMCODIGO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemision whereGUIREMDIRECCIONDESTINATARIO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemision whereGUIREMDIRECCIONPARTIDA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemision whereGUIREMESTABLECIMIENTO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemision whereGUIREMFECHAEMISIONDOCUSUSTENTO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemision whereGUIREMFECHAFIN($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemision whereGUIREMFECHAINICIO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemision whereGUIREMGUIAREMISION($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemision whereGUIREMIDENTIFICACIONDESTINATARIO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemision whereGUIREMIDENTIFICACIONTRANSPORTISTA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemision whereGUIREMMOTIVOTRASLADO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemision whereGUIREMNUMEAUTODOCUSUSTENTO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemision whereGUIREMNUMEDOCUSUSTENTO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemision whereGUIREMOBSERVACION($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemision whereGUIREMPLACA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemision whereGUIREMPTOEMISION($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemision whereGUIREMRAZONSOCIALDESTINATARIO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemision whereGUIREMRAZONSOCIALTRANSPORTISTA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemision whereGUIREMRUTA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemision whereGUIREMSECUENCIAL($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemision whereGUIREMTIPOIDENTIFICACIONTRANSPORTISTA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemision whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemision whereIdempotencyKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemision whereOrdenCompra($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemision wherePedido($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemision whereProcesando($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemision whereRebajado($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemision whereRecibidos($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemision whereTipo($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemision whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemision whereUserId($value)
 */
class GpGuiaRemision extends Model
{
    use HasFactory;
    use HasDeliveryNoteWarehouses;

    protected $table = 'gp_guia_remisions';
    protected $fillable = [
        'tipo',
        'estado',
        'bodega_origen',
        'bodega_destino',
        'bodega_origen_id',
        'bodega_destino_id',
        'pedido',
        'user_id',
        'GUI_REM_BODEGA',
        'GUI_REM_GUIA_REMISION',
        'GUI_REM_ESTABLECIMIENTO',
        'GUI_REM_PTO_EMISION',
        'GUI_REM_SECUENCIAL',
        'GUI_REM_DIRECCION_PARTIDA',
        'GUI_REM_RAZON_SOCIAL_TRANSPORTISTA',
        'GUI_REM_IDENTIFICACION_TRANSPORTISTA',
        'GUI_REM_TIPO_IDENTIFICACION_TRANSPORTISTA',
        'GUI_REM_FECHA_INICIO',
        'GUI_REM_FECHA_FIN',
        'GUI_REM_PLACA',
        'GUI_REM_OBSERVACION',
        'GUI_REM_RAZON_SOCIAL_DESTINATARIO',
        'GUI_REM_DIRECCION_DESTINATARIO',
        'GUI_REM_IDENTIFICACION_DESTINATARIO',
        'GUI_REM_MOTIVO_TRASLADO',
        'GUI_REM_RUTA',
        'GUI_REM_CODI_DOCU_SUSTENTO',
        'GUI_REM_NUME_DOCU_SUSTENTO',
        'GUI_REM_FECHA_EMISION_DOCU_SUSTENTO',
        'rebajado',
        'procesado',
        'GUI_REM_CODIGO',
        'idempotency_key',
        'orden_compra',

        // nuevos campos
        'sucursal_destinatario_id',
        'tipo_despacho_id',
        'proveedor_despacho_id',
        'chofer_transportista_id',
        'numero_orden_compra_gp',
        'monto_orden_compra',
        'requiere_orden_compra'
    ];

    protected $casts = [
        'bodega_origen_id' => 'integer',
        'bodega_destino_id' => 'integer',
        'sucursal_destinatario_id'  => 'integer',
        'tipo_despacho_id'          => 'integer',
        'proveedor_despacho_id'     => 'integer',
        'chofer_transportista_id'   => 'integer',
        'monto_orden_compra'        => 'decimal:2',
        'requiere_orden_compra'     => 'boolean'
    ];

    public function guiadetalle()
    {
        return $this->hasMany(GpGuiaRemisionDetalle::class, 'guia_remision_id');
    }

    public function guiadespacho()
    {
        return $this->hasMany(GpGuiaRemisionDespacho::class, 'guia_remision_id');
    }


    public function guiaBodegaDestino()
    {
        return $this->belongsTo(GpBodega::class, 'bodega_destino', 'BOD_BODEGA');
    }

    public function guiaPuntoVentaDestino()
    {
        return $this->belongsTo(GpRelacionPuntoVentaBodega::class, 'bodega_destino', 'PTO_VTA_BOD_BODEGA');
    }
}
