<?php

namespace App\Models\devoluciones;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use \DateTimeInterface;

/**
 * App\Models\devoluciones\GpDevoluciones
 *
 * @property int $id
 * @property string|null $devolucion
 * @property string $estado
 * @property int $pedido_id
 * @property int $cliente_id
 * @property string|null $pedido
 * @property string|null $factura
 * @property int|null $volver_a_facturar
 * @property string $tipo_devolucion
 * @property int|null $despachado
 * @property int|null $cliente_volver_a_facturar_id
 * @property int|null $user_id
 * @property int|null $sucursal_id
 * @property int|null $sucursal_facturacion_id
 * @property string|null $motivo
 * @property string|null $orden_de_compra
 * @property string $requiere_aprobacion
 * @property string $aprobado
 * @property int|null $aprobador_id
 * @property string|null $aprobado_mensaje
 * @property int|null $devolucion_bodega
 * @property int|null $impuesto
 * @property int|null $procesando
 * @property string $subtotal
 * @property string|null $descuento
 * @property string $subtotalmenosdescuento
 * @property string $iva
 * @property string $total
 * @property string $observacion
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|GpDevoluciones newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpDevoluciones newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpDevoluciones query()
 * @method static \Illuminate\Database\Eloquent\Builder|GpDevoluciones whereAprobado($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpDevoluciones whereAprobadoMensaje($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpDevoluciones whereAprobadorId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpDevoluciones whereClienteVolverAFacturarId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpDevoluciones whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpDevoluciones whereDespachado($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpDevoluciones whereFactura($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpDevoluciones whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpDevoluciones whereMotivo($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpDevoluciones whereOrdenDeCompra($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpDevoluciones wherePedido($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpDevoluciones wherePedidoId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpDevoluciones whereRequiereAprobacion($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpDevoluciones whereSucursalFacturacionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpDevoluciones whereSucursalId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpDevoluciones whereTipoDevolucion($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpDevoluciones whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpDevoluciones whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpDevoluciones whereVolverAFacturar($value)
 * @mixin \Eloquent
 * @property string|null $DOC_GENERADO
 * @property string|null $fecha_NC
 * @property string|null $USERID
 * @property string|null $NC_VTA_MENSAJE
 * @property string|null $AJ_VTA_MENSAJE
 * @method static \Illuminate\Database\Eloquent\Builder|GpDevoluciones whereClienteId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpDevoluciones whereDOCGENERADO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpDevoluciones whereDevolucion($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpDevoluciones whereEstado($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpDevoluciones whereFechaNC($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpDevoluciones whereNCVTAMENSAJE($value)
 */
class GpDevoluciones extends Model
{
    use HasFactory;

    /**
     * Prepare a date for array / JSON serialization.
     *
     * @param  \DateTimeInterface  $date
     * @return string
     */
    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }
}
