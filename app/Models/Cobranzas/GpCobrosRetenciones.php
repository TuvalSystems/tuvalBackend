<?php

namespace App\Models\Cobranzas_temp;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\cobranzas\GpCobrosRetenciones
 *
 * @property int $id
 * @property int $cobro_id
 * @property string $ret_factura
 * @property string $ret_fecha_emision
 * @property string $ret_fecha_vencimiento
 * @property string $ret_subtotal_factura
 * @property string $ret_iva_factura
 * @property string $ret_total_factura
 * @property string $ret_saldo_factura
 * @property string $ret_iva
 * @property string $ret_porcentaje_iva
 * @property string $ret_renta
 * @property string $ret_porcentaje_renta
 * @property string $ret_emision
 * @property string|null $ret_caducidad
 * @property string $ret_comprobante
 * @property string $ret_autorizacion
 * @property string $total
 * @property string|null $codigo_ws_iva
 * @property string|null $mensaje_ws_iva
 * @property string|null $codigo_ws_renta
 * @property string|null $mensaje_ws_renta
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosRetenciones newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosRetenciones newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosRetenciones query()
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosRetenciones whereCobroId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosRetenciones whereCodigoWsIva($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosRetenciones whereCodigoWsRenta($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosRetenciones whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosRetenciones whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosRetenciones whereMensajeWsIva($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosRetenciones whereMensajeWsRenta($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosRetenciones whereRetAutorizacion($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosRetenciones whereRetCaducidad($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosRetenciones whereRetComprobante($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosRetenciones whereRetEmision($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosRetenciones whereRetFactura($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosRetenciones whereRetFechaEmision($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosRetenciones whereRetFechaVencimiento($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosRetenciones whereRetIva($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosRetenciones whereRetIvaFactura($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosRetenciones whereRetPorcentajeIva($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosRetenciones whereRetPorcentajeRenta($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosRetenciones whereRetRenta($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosRetenciones whereRetSaldoFactura($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosRetenciones whereRetSubtotalFactura($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosRetenciones whereRetTotalFactura($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosRetenciones whereTotal($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosRetenciones whereUpdatedAt($value)
 * @mixin \Eloquent
 * @property string|null $ret_iva_srv
 * @property string|null $ret_porcentaje_iva_srv
 * @property string|null $ret_renta_srv
 * @property string|null $ret_porcentaje_renta_srv
 * @property string|null $codigo_ws_iva_srv
 * @property string|null $mensaje_ws_iva_srv
 * @property string|null $codigo_ws_renta_srv
 * @property string|null $mensaje_ws_renta_srv
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosRetenciones whereCodigoWsIvaSrv($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosRetenciones whereCodigoWsRentaSrv($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosRetenciones whereMensajeWsIvaSrv($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosRetenciones whereMensajeWsRentaSrv($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosRetenciones whereRetIvaSrv($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosRetenciones whereRetPorcentajeIvaSrv($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosRetenciones whereRetPorcentajeRentaSrv($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosRetenciones whereRetRentaSrv($value)
 */
class GpCobrosRetenciones extends Model
{
    use HasFactory;
}
