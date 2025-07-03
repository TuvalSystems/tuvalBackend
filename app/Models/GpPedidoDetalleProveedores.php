<?php

namespace App\Models;

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
 * @property string $CANTIDAD
 * @property string $COSTO_UNITARIO
 * @property string $IMPUESTO
 * @property string $TOTAL
 * @property string $TOTALCONIMPUESTO
 * @property string $ingresado
 * @property string $ejecutado
 * @property string|null $NOTA
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedidoDetalleProveedores newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedidoDetalleProveedores newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedidoDetalleProveedores query()
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedidoDetalleProveedores whereCANTIDAD($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedidoDetalleProveedores whereCOSTOUNITARIO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedidoDetalleProveedores whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedidoDetalleProveedores whereDESCRIPCIONARTICULO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedidoDetalleProveedores whereDetalleId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedidoDetalleProveedores whereFECHAENTREGA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedidoDetalleProveedores whereIDPROVEEDOR($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedidoDetalleProveedores whereIMPUESTO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedidoDetalleProveedores whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedidoDetalleProveedores whereNOTA($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedidoDetalleProveedores wherePedidoId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedidoDetalleProveedores whereTOTAL($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedidoDetalleProveedores whereTOTALCONIMPUESTO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedidoDetalleProveedores whereUpdatedAt($value)
 * @mixin \Eloquent
 * @property string|null $recibido
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedidoDetalleProveedores whereRecibido($value)
 * @property string|null $MARGEN
 * @method static \Illuminate\Database\Eloquent\Builder|GpPedidoDetalleProveedores whereMARGEN($value)
 */
class GpPedidoDetalleProveedores extends Model
{
    use HasFactory;



    public function pedidodetalle(){
        return $this->belongsTo(GpPedidoDetalle::class, 'detalle_id');
    }

}

