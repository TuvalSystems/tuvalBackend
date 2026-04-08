<?php

namespace App\Models;

use App\Models\Cobranzas\GpCobrosEfectivoFactura;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\GpCobrosTarjetaMonto
 *
 * @property int $id
 * @property int $cobro_id
 * @property string $monto
 * @property string|null $fecha
 * @property string|null $banco
 * @property string|null $comprobante
 * @property string|null $comentario
 * @property string|null $codigo_ws
 * @property string|null $mensaje_ws
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosTarjetaMonto newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosTarjetaMonto newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosTarjetaMonto query()
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosTarjetaMonto whereBanco($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosTarjetaMonto whereCobroId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosTarjetaMonto whereCodigoWs($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosTarjetaMonto whereComentario($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosTarjetaMonto whereComprobante($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosTarjetaMonto whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosTarjetaMonto whereFecha($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosTarjetaMonto whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosTarjetaMonto whereMensajeWs($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosTarjetaMonto whereMonto($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCobrosTarjetaMonto whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class GpCobrosTarjetaMonto extends Model
{
    use HasFactory;

    public function cobroEfectivoFactura(){
        return $this->hasMany(GpCobrosEfectivoFactura::class, 'cobro_id', 'cobro_id');
    }
}
