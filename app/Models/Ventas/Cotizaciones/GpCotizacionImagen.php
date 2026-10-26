<?php

namespace App\Models\Ventas\Cotizaciones;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\Ventas\Cotizaciones\GpCotizacionImagen
 *
 * @property int $id
 * @property int $cotizacion_id
 * @property string $ruta
 * @property string $nombre_original
 * @property string|null $tipo_mime
 * @property int|null $tamano
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Ventas\Cotizaciones\GpCotizacion $cotizacion
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacionImagen newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacionImagen newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacionImagen query()
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacionImagen whereCotizacionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacionImagen whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacionImagen whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacionImagen whereNombreOriginal($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacionImagen whereRuta($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacionImagen whereTamano($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacionImagen whereTipoMime($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCotizacionImagen whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class GpCotizacionImagen extends Model
{
    use HasFactory;

    protected $table = 'gp_cotizacion_imagenes';
    protected $fillable = [
        'cotizacion_id',
        'ruta',
        'nombre_original',
        'tipo_mime',
        'tamano',
    ];


    public function cotizacion()
    {
        return $this->belongsTo(GpCotizacion::class, 'cotizacion_id');
    }
}
