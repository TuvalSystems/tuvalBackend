<?php

namespace App\Models;

use App\Models\ordendecompra\GpOrdenDeCompra;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\GpPedidoDetalleProveedores
 *
 * @property int $id
 * @property int $pedido_id
 * @property int $detalle_id
 * @property int $ID_PROVEEDOR
 * @property string $DESCRIPCION_ARTICULO
 * @property string $FECHA_ENTREGA
 * @property float $CANTIDAD
 * @property float $COSTO_UNITARIO
 * @property float $IMPUESTO
 * @property float $TOTAL
 * @property float $TOTALCONIMPUESTO
 * @property string|null $NOTA
 * @property float $MARGEN
 * @property string|null $ingresado
 * @property string|null $ejecutado
 * @property string $recibido
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read GpOrdenDeCompra|null $ordenCompra
 * @property-read \App\Models\GpPedidoDetalle $pedidodetalle
 * @property-read \App\Models\GpProveedor|null $proveedores
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedidoDetalleProveedores newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedidoDetalleProveedores newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedidoDetalleProveedores query()
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedidoDetalleProveedores whereCANTIDAD($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedidoDetalleProveedores whereCOSTOUNITARIO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedidoDetalleProveedores whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedidoDetalleProveedores whereDESCRIPCIONARTICULO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedidoDetalleProveedores whereDetalleId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedidoDetalleProveedores whereEjecutado($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedidoDetalleProveedores whereFECHAENTREGA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedidoDetalleProveedores whereIDPROVEEDOR($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedidoDetalleProveedores whereIMPUESTO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedidoDetalleProveedores whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedidoDetalleProveedores whereIngresado($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedidoDetalleProveedores whereMARGEN($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedidoDetalleProveedores whereNOTA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedidoDetalleProveedores wherePedidoId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedidoDetalleProveedores whereRecibido($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedidoDetalleProveedores whereTOTAL($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedidoDetalleProveedores whereTOTALCONIMPUESTO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedidoDetalleProveedores whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class GpPedidoDetalleProveedores extends Model
{
    use HasFactory;
    protected $fillable = [
        'pedido_id',
        'detalle_id',
        'ID_PROVEEDOR',
        'DESCRIPCION_ARTICULO',
        'FECHA_ENTREGA',
        'CANTIDAD',
        'COSTO_UNITARIO',
        'IMPUESTO',
        'TOTAL',
        'TOTALCONIMPUESTO',
        'ingresado',
        'ejecutado',
        'NOTA',
        'recibido',
        'MARGEN',

    ];

    protected $casts = [
        'CANTIDAD' => 'float',
        'COSTO_UNITARIO' => 'float',
        'IMPUESTO' => 'float',
        'TOTAL' => 'float',
        'TOTALCONIMPUESTO' => 'float',
        'MARGEN' => 'float',
    ];

    public function pedidodetalle(){
        return $this->belongsTo(GpPedidoDetalle::class, 'detalle_id');
    }
    public function proveedores(){
        return $this->belongsTo(GpProveedor::class, 'ID_PROVEEDOR', 'id');
    }

    public function ordenCompra(){
        return $this->hasOne(GpOrdenDeCompra::class, 'detalle_proveedores_id', 'id');
    }

}

