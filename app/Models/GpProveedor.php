<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\GpProveedor
 *
 * @property int $id
 * @property string $PROV_RUC
 * @property string|null $PROV_RAZON_SOCIAL
 * @property string|null $PROV_CLASE
 * @property string|null $PROV_ID_DIRECCION
 * @property string|null $PROV_CONTACTO
 * @property string|null $PROV_DIRECCION_1
 * @property string|null $PROV_DIRECCION_2
 * @property string|null $PROV_DIRECCION_3
 * @property string|null $PROV_CIUDAD
 * @property string|null $PROV_PROVINCIA
 * @property string|null $PROV_PAIS
 * @property string|null $PROV_TELEFONO_1
 * @property string|null $PROV_TELEFONO_2
 * @property string|null $PROV_TELEFONO_3
 * @property string|null $PROV_TELEFONO_4
 * @property string|null $PROV_CONDICION_PAGO
 * @property string|null $PROV_DSTO_COMERCIAL
 * @property string $PROV_FECHA_MODIFICACION
 * @property string $PROV_FECHA_CREACION
 * @property string|null $PROV_CORREO_ELECT
 * @property string|null $PROV_IMPUESTO
 * @property string|null $PROV_ESTADO
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|GpProveedor newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpProveedor newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpProveedor query()
 * @method static \Illuminate\Database\Eloquent\Builder|GpProveedor whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpProveedor whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpProveedor wherePROVCIUDAD($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpProveedor wherePROVCLASE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpProveedor wherePROVCONDICIONPAGO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpProveedor wherePROVCONTACTO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpProveedor wherePROVCORREOELECT($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpProveedor wherePROVDIRECCION1($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpProveedor wherePROVDIRECCION2($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpProveedor wherePROVDIRECCION3($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpProveedor wherePROVDSTOCOMERCIAL($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpProveedor wherePROVESTADO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpProveedor wherePROVFECHACREACION($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpProveedor wherePROVFECHAMODIFICACION($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpProveedor wherePROVIDDIRECCION($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpProveedor wherePROVIMPUESTO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpProveedor wherePROVPAIS($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpProveedor wherePROVPROVINCIA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpProveedor wherePROVRAZONSOCIAL($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpProveedor wherePROVRUC($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpProveedor wherePROVTELEFONO1($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpProveedor wherePROVTELEFONO2($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpProveedor wherePROVTELEFONO3($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpProveedor wherePROVTELEFONO4($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpProveedor whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class GpProveedor extends Model
{
    use HasFactory;
}
