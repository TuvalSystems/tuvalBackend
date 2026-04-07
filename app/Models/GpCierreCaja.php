<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\GpCierreCaja
 *
 * @property int $id
 * @property string $CAJA_CODIGO
 * @property string $fecha
 * @property string $estado
 * @property string|null $fecha_cierre
 * @property string|null $fecha_apertura
 * @property string|null $fecha_reapertura
 * @property string|null $datos
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $consulta
 * @method static \Illuminate\Database\Eloquent\Builder|GpCierreCaja newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpCierreCaja newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpCierreCaja query()
 * @method static \Illuminate\Database\Eloquent\Builder|GpCierreCaja whereCAJACODIGO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCierreCaja whereConsulta($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCierreCaja whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCierreCaja whereDatos($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCierreCaja whereEstado($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCierreCaja whereFecha($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCierreCaja whereFechaApertura($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCierreCaja whereFechaCierre($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCierreCaja whereFechaReapertura($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCierreCaja whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCierreCaja whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class GpCierreCaja extends Model
{
    use HasFactory;



    public function caja()
    {
        return $this->belongsTo(GpCaja::class, 'caja_id');
    }
}
