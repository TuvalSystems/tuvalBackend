<?php

namespace App\Models\Cobranzas;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * App\Models\Cobranzas\GpCobrosRetenciones
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
 * @property string|null $ret_iva_srv
 * @property string|null $ret_porcentaje_iva_srv
 * @property string|null $ret_renta_srv
 * @property string|null $ret_porcentaje_renta_srv
 * @property string $ret_emision
 * @property string|null $ret_caducidad
 * @property string $ret_comprobante
 * @property string $ret_autorizacion
 * @property string $total
 * @property string|null $codigo_ws_iva
 * @property string|null $mensaje_ws_iva
 * @property string|null $codigo_ws_renta
 * @property string|null $mensaje_ws_renta
 * @property string|null $codigo_ws_iva_srv
 * @property string|null $mensaje_ws_iva_srv
 * @property string|null $codigo_ws_renta_srv
 * @property string|null $mensaje_ws_renta_srv
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosRetenciones newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosRetenciones newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosRetenciones query()
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosRetenciones whereCobroId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosRetenciones whereCodigoWsIva($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosRetenciones whereCodigoWsIvaSrv($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosRetenciones whereCodigoWsRenta($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosRetenciones whereCodigoWsRentaSrv($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosRetenciones whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosRetenciones whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosRetenciones whereMensajeWsIva($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosRetenciones whereMensajeWsIvaSrv($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosRetenciones whereMensajeWsRenta($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosRetenciones whereMensajeWsRentaSrv($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosRetenciones whereRetAutorizacion($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosRetenciones whereRetCaducidad($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosRetenciones whereRetComprobante($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosRetenciones whereRetEmision($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosRetenciones whereRetFactura($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosRetenciones whereRetFechaEmision($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosRetenciones whereRetFechaVencimiento($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosRetenciones whereRetIva($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosRetenciones whereRetIvaFactura($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosRetenciones whereRetIvaSrv($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosRetenciones whereRetPorcentajeIva($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosRetenciones whereRetPorcentajeIvaSrv($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosRetenciones whereRetPorcentajeRenta($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosRetenciones whereRetPorcentajeRentaSrv($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosRetenciones whereRetRenta($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosRetenciones whereRetRentaSrv($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosRetenciones whereRetSaldoFactura($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosRetenciones whereRetSubtotalFactura($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosRetenciones whereRetTotalFactura($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosRetenciones whereTotal($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosRetenciones whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class GpCobrosRetenciones extends Model
{
    use HasFactory;

    protected $fillable = [
        'cobro_id',
        'ret_factura',
        'ret_fecha_emision',
        'ret_fecha_vencimiento',
        'ret_subtotal_factura',
        'ret_iva_factura',
        'ret_total_factura',
        'ret_saldo_factura',
        'ret_iva',
        'ret_porcentaje_iva',
        'ret_renta',
        'ret_porcentaje_renta',
        'ret_iva_srv',
        'ret_porcentaje_renta_srv',
        'ret_emision',
        'ret_caducidad',
        'ret_comprobante',
        'ret_autorizacion',
        'total',
        'codigo_ws_iva',
        'mensaje_ws_iva',
        'codigo_ws_renta',
        'mensaje_ws_renta',
        'codigo_ws_iva_srv',
        'mensaje_ws_iva_srv',
        'codigo_ws_renta_srv',
        'mensaje_ws_renta_srv'
    ];

    protected $casts = [
        'cobro_id'                  => 'integer',
        'ret_subtotal_factura'      => 'float',
        'ret_iva_factura'           => 'float',
        'ret_total_factura'         => 'float',
        'ret_saldo_factura'         => 'float',
        'ret_iva'                   => 'float',
        'ret_porcentaje_iva'        => 'float',
        'ret_renta'                 => 'float',
        'ret_porcentaje_renta'      => 'float',
        'ret_iva_srv'               => 'float',
        'ret_porcentaje_iva_srv'    => 'float',
        'ret_renta_srv'             => 'float',
        'ret_porcentaje_renta_srv'  => 'float',
        'total'                     => 'float'
    ];



    public function cobro(): BelongsTo
    {
        return $this->belongsTo(GpCobro::class, 'cobro_id', 'id');
    }


    public function cobroPendienteWeb(): BelongsTo
    {
        return $this->belongsTo(GpCobro::class, 'cobro_id', 'id');
            //->whereIn('estado', ['iniciado', 'revisado']);
    }

    public function scopePendientesWeb(Builder $query, ?string $desde = null, ?string $hasta = null): Builder
    {
        return $query->whereHas('cobroPendienteWeb', function ($q) use ($desde, $hasta) {
            if ($desde && $hasta) {
                $q->whereBetween('created_at', [$desde, $hasta]);
            }
        });
    }
}
