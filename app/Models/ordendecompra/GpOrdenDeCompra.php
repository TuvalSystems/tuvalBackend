<?php

namespace App\Models\ordendecompra;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\ordendecompra\GpOrdenDeCompra
 *
 * @property int $id
 * @property int $detalle_id
 * @property int $detalle_proveedores_id
 * @property string $estado
 * @property string $ORDEN_COMPRA_DOCU_ORIGEN
 * @property string $ORDEN_COMPRA_LINEA
 * @property string|null $DOC_GENERADO
 * @property string $ORDEN_COMPRA_FECHA_DOCUMENTO
 * @property string $ORDEN_COMPRA_FECHA_ENTREGA
 * @property string $ORDEN_COMPRA_ID_CLIENTE
 * @property string $ORDEN_COMPRA_ID_PROVEEDOR
 * @property string $ORDEN_COMPRA_ID_DIRECCION
 * @property string|null $ORDEN_COMPRA_NOTA_DOCU
 * @property string $ORDEN_COMPRA_BODEGA
 * @property string $ORDEN_COMPRA_ARTICULO
 * @property string $ORDEN_COMPRA_DESCRIPCION_ARTICULO
 * @property string $ORDEN_COMPRA_UMEDIDA
 * @property string $ORDEN_COMPRA_CANTIDAD
 * @property string $ORDEN_COMPRA_COSTO_UNITARIO
 * @property string $ORDEN_COMPRA_TOTAL
 * @property string $ORDEN_COMPRA_IMPUESTO
 * @property string $ORDEN_COMPRA_ID_USUARIO
 * @property string $ORDEN_COMPRA_MENSAJE
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|GpOrdenDeCompra newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpOrdenDeCompra newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpOrdenDeCompra query()
 * @method static \Illuminate\Database\Eloquent\Builder|GpOrdenDeCompra whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpOrdenDeCompra whereDOCGENERADO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpOrdenDeCompra whereDetalleId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpOrdenDeCompra whereEstado($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpOrdenDeCompra whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpOrdenDeCompra whereORDENCOMPRAARTICULO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpOrdenDeCompra whereORDENCOMPRABODEGA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpOrdenDeCompra whereORDENCOMPRACANTIDAD($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpOrdenDeCompra whereORDENCOMPRACOSTOUNITARIO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpOrdenDeCompra whereORDENCOMPRADESCRIPCIONARTICULO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpOrdenDeCompra whereORDENCOMPRADOCUORIGEN($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpOrdenDeCompra whereORDENCOMPRAFECHADOCUMENTO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpOrdenDeCompra whereORDENCOMPRAFECHAENTREGA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpOrdenDeCompra whereORDENCOMPRAIDCLIENTE($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpOrdenDeCompra whereORDENCOMPRAIDDIRECCION($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpOrdenDeCompra whereORDENCOMPRAIDPROVEEDOR($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpOrdenDeCompra whereORDENCOMPRAIDUSUARIO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpOrdenDeCompra whereORDENCOMPRAIMPUESTO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpOrdenDeCompra whereORDENCOMPRALINEA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpOrdenDeCompra whereORDENCOMPRANOTADOCU($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpOrdenDeCompra whereORDENCOMPRATOTAL($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpOrdenDeCompra whereORDENCOMPRAUMEDIDA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpOrdenDeCompra whereUpdatedAt($value)
 * @mixin \Eloquent
 * @method static \Illuminate\Database\Eloquent\Builder|GpOrdenDeCompra whereDetalleProveedoresId($value)
 */
class GpOrdenDeCompra extends Model
{
    use HasFactory;

    protected $fillable = [
        'detalle_id',
        'detalle_proveedores_id',
        'estado',
        'ORDEN_COMPRA_DOCU_ORIGEN',
        'ORDEN_COMPRA_LINEA',
        'DOC_GENERADO',
        'ORDEN_COMPRA_FECHA_DOCUMENTO',
        'ORDEN_COMPRA_FECHA_ENTREGA',
        'ORDEN_COMPRA_ID_CLIENTE',
        'ORDEN_COMPRA_ID_PROVEEDOR',
        'ORDEN_COMPRA_ID_DIRECCION',
        'ORDEN_COMPRA_NOTA_DOCU',
        'ORDEN_COMPRA_BODEGA',
        'ORDEN_COMPRA_ARTICULO',
        'ORDEN_COMPRA_DESCRIPCION_ARTICULO',
        'ORDEN_COMPRA_UMEDIDA',
        'ORDEN_COMPRA_CANTIDAD',
        'ORDEN_COMPRA_COSTO_UNITARIO',
        'ORDEN_COMPRA_TOTAL',
        'ORDEN_COMPRA_IMPUESTO',
        'ORDEN_COMPRA_ID_USUARIO',
        'ORDEN_COMPRA_MENSAJE',
    ];
}
