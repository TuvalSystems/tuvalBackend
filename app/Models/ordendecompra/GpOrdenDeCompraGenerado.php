<?php

namespace App\Models\ordendecompra;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\ordendecompra\GpOrdenDeCompraGenerado
 *
 * @property int $id
 * @property int $orden_id
 * @property int $detalle_id
 * @property int $detalle_proveedores_id
 * @property string $estado
 * @property string $ORDEN_COMPRA_DOCUMENTO
 * @property string $ORDEN_COMPRA_FECHA_DOCUMENTO
 * @property string $ORDEN_COMPRA_FECHA_ENTREGA
 * @property string $ORDEN_COMPRA_ID_PROVEEDOR
 * @property string $ORDEN_COMPRA_DIRECCION
 * @property string $ORDEN_COMPRA_NOTA_DOCU
 * @property int $recibidos
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|GpOrdenDeCompraGenerado newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpOrdenDeCompraGenerado newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpOrdenDeCompraGenerado query()
 * @method static \Illuminate\Database\Eloquent\Builder|GpOrdenDeCompraGenerado whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpOrdenDeCompraGenerado whereDetalleId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpOrdenDeCompraGenerado whereEstado($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpOrdenDeCompraGenerado whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpOrdenDeCompraGenerado whereORDENCOMPRADIRECCION($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpOrdenDeCompraGenerado whereORDENCOMPRADOCUMENTO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpOrdenDeCompraGenerado whereORDENCOMPRAFECHADOCUMENTO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpOrdenDeCompraGenerado whereORDENCOMPRAFECHAENTREGA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpOrdenDeCompraGenerado whereORDENCOMPRAIDPROVEEDOR($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpOrdenDeCompraGenerado whereORDENCOMPRANOTADOCU($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpOrdenDeCompraGenerado whereOrdenId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpOrdenDeCompraGenerado whereUpdatedAt($value)
 * @mixin \Eloquent
 * @property string|null $ORDEN_COMPRA_RECIBO
 * @property string|null $USERID
 * @method static \Illuminate\Database\Eloquent\Builder|GpOrdenDeCompraGenerado whereDetalleProveedoresId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpOrdenDeCompraGenerado whereORDENCOMPRARECIBO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpOrdenDeCompraGenerado whereUSERID($value)
 */
class GpOrdenDeCompraGenerado extends Model
{
    use HasFactory;
}
