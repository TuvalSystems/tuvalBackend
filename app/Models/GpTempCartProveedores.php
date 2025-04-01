<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\GpTempCartProveedores
 *
 * @property int $id
 * @property int $id_tempcart
 * @property int $ID_PROVEEDOR
 * @property string $DESCRIPCION_ARTICULO
 * @property string $FECHA_ENTREGA
 * @property string $CANTIDAD
 * @property string $COSTO_UNITARIO
 * @property string $IMPUESTO
 * @property string $TOTAL
 * @property string $TOTALCONIMPUESTO
 * @property string|null $NOTA
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|GpTempCartProveedores newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpTempCartProveedores newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpTempCartProveedores query()
 * @method static \Illuminate\Database\Eloquent\Builder|GpTempCartProveedores whereCANTIDAD($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTempCartProveedores whereCOSTOUNITARIO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTempCartProveedores whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTempCartProveedores whereDESCRIPCIONARTICULO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTempCartProveedores whereFECHAENTREGA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTempCartProveedores whereIDPROVEEDOR($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTempCartProveedores whereIMPUESTO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTempCartProveedores whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTempCartProveedores whereIdTempcart($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTempCartProveedores whereNOTA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTempCartProveedores whereTOTAL($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTempCartProveedores whereTOTALCONIMPUESTO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpTempCartProveedores whereUpdatedAt($value)
 * @mixin \Eloquent
 * @property string|null $MARGEN
 * @method static \Illuminate\Database\Eloquent\Builder|GpTempCartProveedores whereMARGEN($value)
 */
class GpTempCartProveedores extends Model
{
    use HasFactory;
}
