<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\GpCotizacionVentaMotivo
 *
 * @property int $id
 * @property string|null $descripcion
 * @property int $recordatorio
 * @property int $estado
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacionVentaMotivo newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacionVentaMotivo newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacionVentaMotivo query()
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacionVentaMotivo whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacionVentaMotivo whereDescripcion($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacionVentaMotivo whereEstado($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacionVentaMotivo whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacionVentaMotivo whereRecordatorio($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacionVentaMotivo whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class GpCotizacionVentaMotivo extends Model
{
    use HasFactory;

    protected $table = 'gp_cotizacion_venta_motivos';
    protected $fillable =[
        'id',
        'descripcion',
        'recordatorio',
        'estado'
    ];
}
