<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\GpCaja
 *
 * @property int $id
 * @property string $CAJA_CODIGO
 * @property string $CAJA_DESCRIPCION
 * @property int $estado
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\GpUsuario> $operariosAsignados
 * @property-read int|null $operarios_asignados_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\GpPuntoVenta> $puntosVenta
 * @property-read int|null $puntos_venta_count
 * @method static \Illuminate\Database\Eloquent\Builder|GpCaja newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpCaja newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|GpCaja query()
 * @method static \Illuminate\Database\Eloquent\Builder|GpCaja whereCAJACODIGO($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCaja whereCAJADESCRIPCION($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCaja whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCaja whereEstado($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCaja whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|GpCaja whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class GpCaja extends Model
{
    use HasFactory;



    public function operariosAsignados()
    {
        return $this->belongsToMany(GpUsuario::class, 'gp_relacion_usuario_cajas', 'caja_id', 'user_id')
            ->wherePivot('estado', 1);
    }


    public function puntosVenta()
    {
        return $this->belongsToMany(GpPuntoVenta::class, 'gp_relacion_punto_venta_cajas', 'caja_id', 'pv_id')
            ->wherePivot('estado', 1);
    }
}
