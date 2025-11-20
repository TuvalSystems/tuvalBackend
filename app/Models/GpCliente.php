<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * GpCliente
 *
 * @property int $id
 * @property string $CLI_RUC
 * @property string|null $CLI_RAZON_SOCIAL
 * @property string|null $CLI_NOMBRE_CORTO
 * @property string|null $CLI_CLASE
 * @property int|null $CLI_PRIORIDAD
 * @property string|null $CLI_ID_DIRECCION
 * @property string|null $CLI_CONTACTO
 * @property string|null $CLI_DIRECCION
 * @property string|null $CLI_CIUDAD
 * @property string|null $CLI_PROVINCIA
 * @property string|null $CLI_TERRITORIO
 * @property string|null $CLI_ESTADO
 * @property string|null $CLI_SUSPENDIDO
 * @property string|null $CLI_TELEFONO_1
 * @property string|null $CLI_TELEFONO_2
 * @property string|null $CLI_TELEFONO_3
 * @property string|null $CLI_TELEFONO_4
 * @property string $CLI_FECHA_MODIFICACION
 * @property string $CLI_FECHA_CREACION
 * @property string|null $CLI_CORREO_ELECT_SRI
 * @property string|null $CLI_CORREO_ELECT_NOTIFICACION_INTERNA
 * @property string|null $CLI_IMPUESTO
 * @property string|null $CLI_DIRECCION_ENVIO
 * @property string|null $CLI_DIRECCION_FACTURA
 * @property string|null $CLI_VENDEDOR
 * @property string|null $CLI_DSTO_COMERCIAL
 * @property string|null $CLI_CONDICION_PAGO
 * @property int|null $CLI_DIA_GRACIA_VTA_CREDITO
 * @property int|null $CLI_DIA_GRACIA_CHEQUE_A_FECHA
 * @property string|null $CLI_NIVEL_PRECIO
 * @property string|null $CLI_TIPO_CREDITO
 * @property string|null $CLI_MONTO_CREDITO
 * @property string|null $CLI_GRUPO_ECONOMICO
 * @property string|null $CLI_GRUPO
 * @property string|null $CLI_SUBGRUPO
 * @property int|null $CLI_DIA_MAXIMO_MES_FACTURACION
 * @property string|null $CLI_FACTURA_POSTERIOR_AL_DESPACHO
 * @property int|null $CLI_SEGMENTO_ID
 * @property string|null $CLI_SUBSEGMENTO_ID
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|GpCliente newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpCliente newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpCliente query()
 * @method static \Illuminate\Database\Eloquent\Builder|GpCliente whereCLICIUDAD($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCliente whereCLICLASE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCliente whereCLICONDICIONPAGO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCliente whereCLICONTACTO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCliente whereCLICORREOELECTNOTIFICACIONINTERNA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCliente whereCLICORREOELECTSRI($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCliente whereCLIDIAGRACIACHEQUEAFECHA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCliente whereCLIDIAGRACIAVTACREDITO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCliente whereCLIDIAMAXIMOMESFACTURACION($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCliente whereCLIDIRECCION($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCliente whereCLIDIRECCIONENVIO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCliente whereCLIDIRECCIONFACTURA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCliente whereCLIDSTOCOMERCIAL($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCliente whereCLIESTADO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCliente whereCLIFACTURAPOSTERIORALDESPACHO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCliente whereCLIFECHACREACION($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCliente whereCLIFECHAMODIFICACION($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCliente whereCLIGRUPO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCliente whereCLIGRUPOECONOMICO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCliente whereCLIIDDIRECCION($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCliente whereCLIIMPUESTO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCliente whereCLIMONTOCREDITO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCliente whereCLINIVELPRECIO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCliente whereCLINOMBRECORTO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCliente whereCLIPRIORIDAD($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCliente whereCLIPROVINCIA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCliente whereCLIRAZONSOCIAL($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCliente whereCLIRUC($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCliente whereCLISUBGRUPO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCliente whereCLISUSPENDIDO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCliente whereCLITELEFONO1($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCliente whereCLITELEFONO2($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCliente whereCLITELEFONO3($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCliente whereCLITELEFONO4($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCliente whereCLITERRITORIO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCliente whereCLITIPOCREDITO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCliente whereCLIVENDEDOR($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCliente whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCliente whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCliente whereUpdatedAt($value)
 *
 *
 *
 * @property  $suspendido
 * @property  $cupo_disponible
 * @property $cupo_asignado
 */

class GpCliente extends Model
{
    use HasFactory;
    protected $fillable = [
        'CLI_RUC',
        'CLI_RAZON_SOCIAL',
        'CLI_NOMBRE_CORTO',
        'CLI_CLASE',
        'CLI_PRIORIDAD',
        'CLI_ID_DIRECCION',
        'CLI_CONTACTO',
        'CLI_DIRECCION',
        'CLI_CIUDAD',
        'CLI_PROVINCIA',
        'CLI_TERRITORIO',
        'CLI_ESTADO',
        'CLI_SUSPENDIDO',
        'CLI_TELEFONO_1',
        'CLI_TELEFONO_2',
        'CLI_TELEFONO_3',
        'CLI_TELEFONO_4',
        'CLI_FECHA_MODIFICACION',
        'CLI_FECHA_CREACION',
        'CLI_CORREO_ELECT_SRI',
        'CLI_CORREO_ELECT_NOTIFICACION_INTERNA',
        'CLI_IMPUESTO',
        'CLI_DIRECCION_ENVIO',
        'CLI_DIRECCION_FACTURA',
        'CLI_VENDEDOR',
        'CLI_DSTO_COMERCIAL',
        'CLI_CONDICION_PAGO',
        'CLI_DIA_GRACIA_VTA_CREDITO',
        'CLI_DIA_GRACIA_CHEQUE_A_FECHA',
        'CLI_NIVEL_PRECIO',
        'CLI_TIPO_CREDITO',
        'CLI_MONTO_CREDITO',
        'CLI_GRUPO_ECONOMICO',
        'CLI_GRUPO',
        'CLI_SUBGRUPO',
        'CLI_DIA_MAXIMO_MES_FACTURACION',
        'CLI_FACTURA_POSTERIOR_AL_DESPACHO',
        'CLI_SEGMENTO_ID',
        'CLI_SUBSEGMENTO_ID'
    ];




    public function gpvendedor(){
        return $this->belongsTo(GpVendedor::class, 'CLI_VENDEDOR', 'VEND_ID');
    }
}
