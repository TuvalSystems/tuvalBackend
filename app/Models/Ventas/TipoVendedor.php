<?php

namespace App\Models\Ventas;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\Ventas\TipoVendedor
 *
 * @property int $id
 * @property int $tipo_vendedor
 * @property string $codigo
 * @property string $descripcion
 * @property int $estado
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|TipoVendedor newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|TipoVendedor newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|TipoVendedor query()
 * @method static \Illuminate\Database\Eloquent\Builder|TipoVendedor whereCodigo($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TipoVendedor whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TipoVendedor whereDescripcion($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TipoVendedor whereEstado($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TipoVendedor whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TipoVendedor whereTipoVendedor($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TipoVendedor whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class TipoVendedor extends Model
{
    use HasFactory;

    protected $table = 'tipo_vendedors';
    protected $fillable = [
        'tipo_vendedor',
        'codigo',
        'descripcion',
        'estado',
    ];
}
