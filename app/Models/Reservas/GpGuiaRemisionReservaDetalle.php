<?php

namespace App\Models\Reservas;

use App\Models\GpCotizacion;
use App\Models\GpCotizacionDetalle;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\Reservas\GpGuiaRemisionReservaDetalle
 *
 * @property int $id
 * @property int|null $guia_id
 * @property int|null $traslado_id
 * @property int|null $cotizacion_id
 * @property int|null $detalle_id
 * @property int|null $stock_id
 * @property string|null $art_code
 * @property string|null $art_descripcion
 * @property string|null $art_cod_tres_letras
 * @property string|null $cantidad
 * @property string|null $art_plan_umedida
 * @property string|null $recibido
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read GpCotizacion|null $cotizacion
 * @property-read GpCotizacionDetalle|null $detallecotizacion
 * @property-read float $pending_received
 * @property-read \App\Models\Reservas\GpGuiaRemisionReserva|null $guia
 * @property-read \App\Models\Reservas\GpTrasladosReservas|null $traslado
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemisionReservaDetalle newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemisionReservaDetalle newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemisionReservaDetalle query()
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemisionReservaDetalle whereArtCodTresLetras($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemisionReservaDetalle whereArtCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemisionReservaDetalle whereArtDescripcion($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemisionReservaDetalle whereArtPlanUmedida($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemisionReservaDetalle whereCantidad($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemisionReservaDetalle whereCotizacionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemisionReservaDetalle whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemisionReservaDetalle whereDetalleId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemisionReservaDetalle whereGuiaId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemisionReservaDetalle whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemisionReservaDetalle whereRecibido($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemisionReservaDetalle whereStockId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemisionReservaDetalle whereTrasladoId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpGuiaRemisionReservaDetalle whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class GpGuiaRemisionReservaDetalle extends Model
{
    use HasFactory;

    protected $appends = ['pending_received'];
    protected $cast = [
        'pending_received' => 'float'
    ];
    protected $fillable = [
        'guia_id',
        'traslado_id',
        'cotizacion_id',
        'detalle_id',
        'stock_id',
        'art_code',
        'art_descripcion',
        'art_cod_tres_letras',
        'cantidad',
        'art_plan_umedida',
        'recibido'
    ];


    public function guia(){
        return $this->belongsTo(GpGuiaRemisionReserva::class, 'guia_id');
    }

    public function traslado()
    {
        return $this->belongsTo(GpTrasladosReservas::class, 'traslado_id');
    }

    public function cotizacion(){
        return $this->belongsTo(GpCotizacion::class, 'cotizacion_id');
    }

    public function detallecotizacion(){
        return $this->belongsTo(GpCotizacionDetalle::class, 'detalle_id' , 'id');
    }


    public function getpendingReceivedAttribute(): float{
        $cant = $this->cantidad ?? 0;
        $rec = $this->recibido ?? 0;
        return max(0, $cant - $rec);
    }


}
