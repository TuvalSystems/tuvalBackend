<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\GpCobrosAplicacionMonto
 *
 * @property int $id
 * @property int $cobro_id
 * @property string $monto
 * @property string|null $cobro
 * @property string|null $tipo
 * @property string|null $comentario
 * @property string|null $codigo_ws
 * @property string|null $mensaje_ws
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\GpCobrosAplicacionFactura> $facturasAplicadas
 * @property-read int|null $facturas_aplicadas_count
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosAplicacionMonto newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosAplicacionMonto newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosAplicacionMonto query()
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosAplicacionMonto whereCobro($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosAplicacionMonto whereCobroId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosAplicacionMonto whereCodigoWs($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosAplicacionMonto whereComentario($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosAplicacionMonto whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosAplicacionMonto whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosAplicacionMonto whereMensajeWs($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosAplicacionMonto whereMonto($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosAplicacionMonto whereTipo($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosAplicacionMonto whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class GpCobrosAplicacionMonto extends Model
{
    use HasFactory;

    protected $fillable = [
        'id',
        'monto',
        'cobro',
        'cobro_id',
        'tipo',
        'comentario',
        'codigo_ws',
        'mensaje_ws',
    ];


    public function facturasAplicadas()
    {
        return $this->hasMany(GpCobrosAplicacionFactura::class, 'aplicacion_id', 'id');
    }
}
